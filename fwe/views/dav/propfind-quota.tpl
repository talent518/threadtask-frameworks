<?xml version="1.0" encoding="utf-8"?>
<D:multistatus xmlns:D="DAV:" xmlns:ns0="DAV:">
<D:response xmlns:g0="DAV:">
<D:href>/{$this:route}{$file}</D:href>
<D:propstat>
<D:prop>
<g0:quota-available-bytes>{$available}</g0:quota-available-bytes>
<g0:quota-used-bytes>{$used}</g0:quota-used-bytes>
</D:prop>
<D:status>HTTP/1.1 200 OK</D:status>
</D:propstat>
</D:response>
</D:multistatus>