# California Civic Intelligence

**Live site:** [californiacivic.com](https://californiacivic.com)  
**Platform:** WordPress + Custom Plugin  
**Status:** MVP Live ✅

---

California Civic Intelligence is an independent, nonpartisan platform covering California state government, politics, policy, courts, agencies, and public finance. We publish guest commentary, civic explainers, and AI-assisted briefs — all editor-reviewed.

## What This Repo Contains

```
wp-content/
  plugins/
    ca-civic-intelligence/          # Main plugin
      ca-civic-intelligence.php     # Plugin entry point + autoloader
      includes/
        class-activator.php         # DB tables (dbDelta), activation logic
        class-deactivator.php       # Deactivation cleanup
        class-post-types.php        # 9 CPTs: opinion, ai_brief, explainer, etc.
        class-taxonomies.php        # 7 taxonomies: issue_area, branch, region, etc.
        class-rest-api.php          # HMAC-authenticated ingestion endpoints
        class-admin.php             # Meta boxes, AI review gate, settings page
        class-submission.php        # Guest submission shortcode + AJAX handler
      assets/js/
        submission.js               # jQuery submission form handler
    ca-civic-rewrite-fixer/         # CPT archive URL fix for nginx
      ca-civic-rewrite-fixer.php    # Explicit add_rewrite_rule() for CPT archives
```

## Custom Post Types

| Slug | Label | Notes |
|------|-------|-------|
| `ca_opinion` | Opinions | Guest commentary, editor-approved |
| `ca_ai_brief` | AI Briefs | Drafts only — requires `_ca_ai_reviewed` meta |
| `ca_explainer` | Explainers | Reference articles |
| `ca_public_event` | Public Events | Legislative hearings, etc. |
| `ca_bill` | Bills | Bill tracking |
| `ca_reg_docket` | Reg Dockets | Regulatory proceedings |
| `ca_agency` | Agencies | Reference entries |
| `ca_submission` | Submissions | **Private** — guest submissions queue |
| `ca_promotion` | Promotions | **Private** — sponsored distribution requests |

## Taxonomies

`ca_issue_area`, `ca_branch`, `ca_agency_tax`, `ca_region`, `ca_source_type`, `ca_audience_segment`, `ca_content_label`

## REST API

Namespace: `ca-civic/v1`

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/status` | GET | Public | Health check |
| `/ingest/record` | POST | HMAC | Ingest any record type |
| `/ingest/brief` | POST | HMAC | Ingest AI brief (creates draft) |
| `/ingest/event` | POST | HMAC | Ingest public event |
| `/ingest/bill` | POST | HMAC | Ingest bill |
| `/ingest/docket` | POST | HMAC | Ingest regulatory docket |

HMAC authentication: `X-CA-Civic-Sig: sha256=<hmac-sha256-hex>` header required.

## wp-config.php Constants Required

```php
define( 'CA_CIVIC_HMAC_SECRET', 'your-secret-here' );
define( 'CA_CIVIC_OPENAI_KEY', 'sk-...' );
define( 'CA_CIVIC_WORKER_TOKEN', 'your-worker-token-here' );
```

**Never commit actual secret values to this repo.**

## Critical Rules

- `ca_civic_auto_publish_ai` is **permanently set to `'0'`** in the Activator — AI content NEVER auto-publishes
- - All AI briefs are created as `draft` status and require `_ca_ai_reviewed = '1'` meta before publication
  - - Money can amplify approved content; money cannot buy publication
    - - All admin actions require `current_user_can()` checks
      - - All SQL must use `$wpdb->prepare()`
       
        - ## Infrastructure
       
        - - **Hosting:** SiteGround GoGeek (nginx)
          - - **Domain:** Namecheap → californiacivic.com
            - - **Theme:** Kadence + Kadence Blocks
              - - **Newsletter:** MailPoet
                - - **Forms:** WPForms
                  - - **SEO:** Yoast SEO
                   
                    - ## Next Steps
                   
                    - - [ ] External Python/Node ingestion worker
                      - [ ] - [ ] GitHub Actions CI/CD deployment workflow
                      - [ ] - [ ] Staging environment (SiteGround Site Tools)
                      - [ ] - [ ] MailPoet newsletter list + welcome email
                      - [ ] - [ ] Wordfence security configuration
                      - [ ] - [ ] Stripe integration for sponsored distribution
                     
                      - [ ] ## License
                     
                      - [ ] GPL-3.0 — see [LICENSE](LICENSE)
                      - [ ] 
