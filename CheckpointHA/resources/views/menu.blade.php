<a href="{{ $url }}" id="cp-cluster-ips-link"><i class="fa fa-coffee fa-fw fa-lg" aria-hidden="true"></i> Check Point Cluster IPs</a>

<script>
    (function () {
        var link = document.getElementById('cp-cluster-ips-link');
        if (! link) {
            return;
        }

        link.addEventListener('click', function (e) {
            e.preventDefault();

            var overlay = document.getElementById('cp-loading-overlay');
            if (! overlay) {
                overlay = document.createElement('div');
                overlay.id = 'cp-loading-overlay';
                overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.85);z-index:99999;display:flex;align-items:center;justify-content:center;';
                overlay.innerHTML = '<div style="font-size:18px;color:#333;"><i class="fa fa-spinner fa-pulse fa-fw" aria-hidden="true" style="margin-right:10px;"></i>Loading data...</div>';
                document.body.appendChild(overlay);
            }

            // give the overlay a moment to render before navigating
            setTimeout(function () {
                window.location.href = link.href;
            }, 50);
        });
    })();
</script>
