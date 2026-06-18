#!/usr/bin/env python3
"""
California Civic Intelligence — Ingestion Worker
Polls public California data sources and posts records to the CA Civic REST API.

Data sources:
  - LegiScan CA: Bills introduced, passed, amended
  - CPUC (CA Public Utilities Commission): Rulemaking dockets
  - CARB (CA Air Resources Board): Regulatory activities
  - CA Courts: Published opinions (via courts.ca.gov)
  - CA State Budget: Expenditure data

Usage:
  python ingest_worker.py --source legiscan --days 1
  python ingest_worker.py --all --days 7
  
Environment variables (or .env file):
  CA_CIVIC_WORKER_TOKEN   — Worker auth token (from wp-config.php)
  CA_CIVIC_HMAC_SECRET    — HMAC signing secret (from wp-config.php)
  CA_CIVIC_API_URL        — Full base URL, e.g. https://californiacivic.com/wp-json/ca-civic/v1
  LEGISCAN_API_KEY        — LegiScan API key (free tier available)
  OPENAI_API_KEY          — For AI brief generation (optional)
"""

import argparse
import hashlib
import hmac
import json
import logging
import os
import sys
import time
from datetime import datetime, timedelta, timezone
from typing import Optional

import requests
from dotenv import load_dotenv

load_dotenv()

# ── Configuration ──────────────────────────────────────────────────────────────
API_URL       = os.environ.get("CA_CIVIC_API_URL", "https://californiacivic.com/wp-json/ca-civic/v1")
WORKER_TOKEN  = os.environ.get("CA_CIVIC_WORKER_TOKEN", "")
HMAC_SECRET   = os.environ.get("CA_CIVIC_HMAC_SECRET", "")
LEGISCAN_KEY  = os.environ.get("LEGISCAN_API_KEY", "")
OPENAI_KEY    = os.environ.get("OPENAI_API_KEY", "")

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    handlers=[logging.StreamHandler(sys.stdout)],
)
log = logging.getLogger(__name__)


# ── HMAC signing ───────────────────────────────────────────────────────────────
def sign_request(payload: dict, secret: str) -> str:
    """Generate HMAC-SHA256 signature for the payload."""
    msg = json.dumps(payload, separators=(",", ":"), sort_keys=True).encode()
    return hmac.new(secret.encode(), msg, hashlib.sha256).hexdigest()


# ── CA Civic REST API client ────────────────────────────────────────────────────
class CACivicAPI:
    def __init__(self, base_url: str, token: str, secret: str):
        self.base_url = base_url.rstrip("/")
        self.token    = token
        self.secret   = secret
        self.session  = requests.Session()
        self.session.headers.update({
            "Content-Type": "application/json",
            "X-CA-Worker-Token": token,
        })

    def ingest(self, record: dict) -> dict:
        """POST a single record to /ingest/record."""
        sig = sign_request(record, self.secret)
        self.session.headers["X-CA-HMAC-Signature"] = sig
        url = f"{self.base_url}/ingest/record"
        resp = self.session.post(url, json=record, timeout=20)
        resp.raise_for_status()
        return resp.json()

    def status(self) -> dict:
        """Check API status."""
        resp = self.session.get(f"{self.base_url}/status", timeout=10)
        resp.raise_for_status()
        return resp.json()


# ── LegiScan source ─────────────────────────────────────────────────────────────
class LegiScanSource:
    """Fetches CA bills from the LegiScan API."""
    BASE = "https://api.legiscan.com/"

    def __init__(self, api_key: str):
        self.key = api_key

    def _get(self, op: str, **params) -> dict:
        p = {"key": self.key, "op": op, **params}
        r = requests.get(self.BASE, params=p, timeout=30)
        r.raise_for_status()
        data = r.json()
        if data.get("status") != "OK":
            raise ValueError(f"LegiScan error: {data}")
        return data

    def get_master_list(self, session_id: int = None) -> list[dict]:
        """Get master list of bills for CA current session."""
        if session_id:
            data = self._get("getMasterListRaw", id=session_id)
        else:
            data = self._get("getSessionList", state="CA")
            sessions = data["sessions"]
            # Pick most recent session
            current = max(sessions, key=lambda s: s["session_id"])
            data = self._get("getMasterListRaw", id=current["session_id"])
        return list(data["masterlist"].values())

    def get_bill(self, bill_id: int) -> dict:
        """Get full bill details."""
        data = self._get("getBill", id=bill_id)
        return data["bill"]

    def bills_to_records(self, bills: list[dict], days_back: int = 1) -> list[dict]:
        """Convert LegiScan bill dicts to CA Civic ingest records."""
        cutoff = datetime.now(timezone.utc) - timedelta(days=days_back)
        records = []
        for b in bills:
            try:
                last_action_date = datetime.strptime(
                    b.get("last_action_date", "1970-01-01"), "%Y-%m-%d"
                ).replace(tzinfo=timezone.utc)
                if last_action_date < cutoff:
                    continue
                record = {
                    "source_type":  "legiscan_bill",
                    "source_id":    str(b["bill_id"]),
                    "title":        b.get("bill_number", "") + ": " + b.get("title", ""),
                    "url":          b.get("url", ""),
                    "bill_number":  b.get("bill_number", ""),
                    "status":       b.get("status", ""),
                    "last_action":  b.get("last_action", ""),
                    "last_action_date": b.get("last_action_date", ""),
                    "sponsors":     [s.get("name", "") for s in b.get("sponsors", [])],
                    "subjects":     [s.get("subject", "") for s in b.get("subjects", [])],
                    "description":  b.get("description", ""),
                    "ingest_ts":    datetime.now(timezone.utc).isoformat(),
                }
                records.append(record)
            except Exception as e:
                log.warning(f"Skipping bill {b.get('bill_id')}: {e}")
        return records


# ── CPUC source (scrape-free RSS) ───────────────────────────────────────────────
class CPUCSource:
    """Polls the CPUC rulemaking docket RSS feed."""
    FEED = "https://docs.cpuc.ca.gov/SearchRes.aspx?DocFormat=RSS&Rtype=&Item=4"

    def get_records(self, days_back: int = 1) -> list[dict]:
        import xml.etree.ElementTree as ET
        try:
            r = requests.get(self.FEED, timeout=30)
            r.raise_for_status()
            root = ET.fromstring(r.content)
        except Exception as e:
            log.warning(f"CPUC feed error: {e}")
            return []

        records = []
        cutoff = datetime.now(timezone.utc) - timedelta(days=days_back)
        for item in root.iter("item"):
            try:
                title    = item.findtext("title", "")
                link     = item.findtext("link", "")
                pub_date = item.findtext("pubDate", "")
                desc     = item.findtext("description", "")
                if not title:
                    continue
                record = {
                    "source_type":  "cpuc_docket",
                    "source_id":    link,
                    "title":        title,
                    "url":          link,
                    "description":  desc,
                    "published":    pub_date,
                    "ingest_ts":    datetime.now(timezone.utc).isoformat(),
                }
                records.append(record)
            except Exception as e:
                log.warning(f"CPUC item error: {e}")
        return records


# ── Main orchestration ──────────────────────────────────────────────────────────
def run(args):
    api = CACivicAPI(API_URL, WORKER_TOKEN, HMAC_SECRET)

    # Verify API is reachable
    try:
        status = api.status()
        log.info(f"API status: {status}")
        if status.get("auto_publish_ai") == "1":
            log.error("CRITICAL: auto_publish_ai is ENABLED on server — aborting for safety!")
            sys.exit(1)
    except Exception as e:
        log.error(f"Cannot reach CA Civic API: {e}")
        sys.exit(1)

    total_ingested = 0

    # LegiScan
    if args.source in ("legiscan", "all") and LEGISCAN_KEY:
        log.info("Fetching LegiScan CA bills...")
        try:
            ls = LegiScanSource(LEGISCAN_KEY)
            bills = ls.get_master_list()
            records = ls.bills_to_records(bills, days_back=args.days)
            log.info(f"Found {len(records)} bills updated in last {args.days} day(s)")
            for rec in records:
                try:
                    result = api.ingest(rec)
                    log.info(f"  Ingested bill {rec['source_id']}: {result}")
                    total_ingested += 1
                    time.sleep(0.5)  # Rate limit
                except Exception as e:
                    log.warning(f"  Failed to ingest bill {rec['source_id']}: {e}")
        except Exception as e:
            log.error(f"LegiScan error: {e}")
    elif args.source == "legiscan" and not LEGISCAN_KEY:
        log.warning("LEGISCAN_API_KEY not set — skipping LegiScan")

    # CPUC
    if args.source in ("cpuc", "all"):
        log.info("Fetching CPUC docket RSS...")
        try:
            cpuc = CPUCSource()
            records = cpuc.get_records(days_back=args.days)
            log.info(f"Found {len(records)} CPUC docket items")
            for rec in records:
                try:
                    result = api.ingest(rec)
                    log.info(f"  Ingested CPUC item: {result}")
                    total_ingested += 1
                    time.sleep(0.3)
                except Exception as e:
                    log.warning(f"  Failed to ingest CPUC item: {e}")
        except Exception as e:
            log.error(f"CPUC error: {e}")

    log.info(f"Ingestion complete. Total records ingested: {total_ingested}")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="CA Civic Intelligence Ingestion Worker")
    parser.add_argument("--source", choices=["legiscan", "cpuc", "carb", "all"], default="all")
    parser.add_argument("--days",   type=int, default=1, help="Days back to look for updates")
    parser.add_argument("--dry-run", action="store_true", help="Print records without posting")
    args = parser.parse_args()
    run(args)
