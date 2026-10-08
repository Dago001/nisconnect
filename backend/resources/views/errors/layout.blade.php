<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>{{ $title }} · NISconnect</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:Arial,Helvetica,sans-serif;background:#F4F6F5;color:#14201A}
.box{text-align:center;max-width:460px;padding:32px}.code{font-size:56px;font-weight:700;color:#0B6B3A}a{display:inline-block;margin-top:16px;background:#0B6B3A;color:#fff;padding:10px 16px;border-radius:9px;text-decoration:none}</style></head>
<body><div class="box"><img src="/portal-assets/nis-logo.jpg" alt="" width="64" height="64" style="border-radius:12px"><div class="code">{{ $code }}</div><h1>{{ $title }}</h1>
<p>{{ (isset($exception) && $code === '403' && $exception->getMessage()) ? $exception->getMessage() : $message }}</p><a href="/admin">Back to the portal</a></div></body></html>
