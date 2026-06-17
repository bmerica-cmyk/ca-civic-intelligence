(function($) {
    $(document).ready(function() {
        $('#ca-opinion-form').on('submit', function(e) {
            e.preventDefault();
            var $btn = $(this).find('button[type="submit"]');
            var $result = $('#ca-submission-result');
            $btn.prop('disabled', true).text('Submitting...');
            $result.html('');
            $.ajax({
                url: caCivicAjax.url,
                method: 'POST',
                data: $(this).serialize() + '&_ajax_nonce=' + caCivicAjax.nonce,
                success: function(res) {
                    if (res.success) {
                        $result.html('<div style="padding:12px;background:#d4edda;color:#155724;border-radius:4px;margin-top:12px;">' + res.data.message + '</div>');
                        $('#ca-opinion-form')[0].reset();
                    } else {
                        $result.html('<div style="padding:12px;background:#f8d7da;color:#721c24;border-radius:4px;margin-top:12px;">' + (res.data.message || 'An error occurred.') + '</div>');
                    }
                },
                error: function() {
                    $result.html('<div style="padding:12px;background:#f8d7da;color:#721c24;border-radius:4px;margin-top:12px;">Network error. Please try again.</div>');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Submit for Editorial Review');
                }
            });
        });
    });
})(jQuery);
