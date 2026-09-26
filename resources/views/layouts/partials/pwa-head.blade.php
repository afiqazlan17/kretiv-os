{{-- "Add to Home Screen" support — iOS ignores manifest.json almost
     entirely, so the apple-* meta tags below are what actually make it
     launch full-screen with its own icon instead of opening Safari with
     the address bar. The manifest.json is for Android/Chrome, which does
     read it properly.

     The icon files carry a 7-day cache-control header (public/.htaccess
     doesn't special-case them) and Safari/iOS caches apple-touch-icon.png
     by URL regardless of removing and re-adding the Home Screen bookmark
     — a filename that never changes means a design tweak never reaches a
     phone that already fetched it once. ?v= (the file's mtime) busts that
     cache automatically whenever the icon is regenerated. --}}
@php $iconV = file_exists(public_path('apple-touch-icon.png')) ? filemtime(public_path('apple-touch-icon.png')) : 1; @endphp
<link rel="manifest" href="{{ asset('manifest.json') }}?v={{ $iconV }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v={{ $iconV }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}?v={{ $iconV }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v={{ $iconV }}">
<meta name="theme-color" content="#100904">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Kretiv OS">
