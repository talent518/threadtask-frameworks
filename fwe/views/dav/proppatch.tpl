<?xml version="1.0" encoding="utf-8"?>
<D:multistatus xmlns:D="DAV:" xmlns:ns1="http://apache.org/dav/props/" xmlns:ns0="DAV:">
	<D:response>
		<D:href>/{$this:route}{$file}</D:href>
		<D:propstat>
			<D:prop>
				<ns1:executable />
			</D:prop>
			<D:status>HTTP/1.1 200 OK</D:status>
		</D:propstat>
	</D:response>
</D:multistatus>
