{{-- "Add to Home Screen" support — iOS ignores manifest.json almost
     entirely, so the apple-* meta tags below are what actually make it
     launch full-screen with its own icon instead of opening Safari with
     the address bar. The manifest.json is for Android/Chrome, which does
     read it properly. --}}
<link rel="manifest" href="{{ asset('manifest.json') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<meta name="theme-color" content="#100904">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Kretiv OS">
