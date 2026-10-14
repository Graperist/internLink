{{-- Progressive Web App: install on the home screen + offline page. Included in every layout's <head>. --}}
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#1c3f66">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Intern-Link">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="apple-touch-icon" href="/images/icons/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="192x192" href="/images/icons/icon-192.png">
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {});
        });
    }
</script>
