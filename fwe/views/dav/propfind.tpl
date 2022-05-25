<?xml version="1.0" encoding="utf-8"?>
<D:multistatus xmlns:D="DAV:" xmlns:ns1="http://apache.org/dav/props/" xmlns:ns0="DAV:">
	<D:response xmlns:lp1="DAV:" xmlns:lp2="http://apache.org/dav/props/" xmlns:g0="DAV:" xmlns:g1="http://apache.org/dav/props/">
		<D:href>/{$this:route}{$file}</D:href>
		<D:propstat>
			<D:prop>
				<lp1:resourcetype>
					<D:collection />
				</lp1:resourcetype>
				<lp1:getetag>"{php printf('%xT-%xO', $stat['mtime'], $stat['size'])}"</lp1:getetag>
				<lp1:getlastmodified>{$stat.mtime|gmt}</lp1:getlastmodified>
			</D:prop>
			<D:status>HTTP/1.1 200 OK</D:status>
		</D:propstat>
	</D:response>
{loop $files $f $stat}
{if substr($f, -1) === '/'}
	<D:response xmlns:lp1="DAV:" xmlns:lp2="http://apache.org/dav/props/" xmlns:g0="DAV:" xmlns:g1="http://apache.org/dav/props/">
		<D:href>/{$this:route}{$file}{$f}</D:href>
		<D:propstat>
			<D:prop>
				<lp1:resourcetype>
					<D:collection />
				</lp1:resourcetype>
				<lp1:getetag>"{php printf('%xT-%xO', $stat['mtime'], $stat['size'])}"</lp1:getetag>
				<lp1:getlastmodified>{$stat.mtime|gmt}</lp1:getlastmodified>
			</D:prop>
			<D:status>HTTP/1.1 200 OK</D:status>
		</D:propstat>
	</D:response>
{else}
	<D:response xmlns:lp1="DAV:" xmlns:lp2="http://apache.org/dav/props/">
		<D:href>/{$this:route}{$file}{$f}</D:href>
		<D:propstat>
			<D:prop>
				<lp1:resourcetype />
				<lp1:getcontentlength>{$stat.size}</lp1:getcontentlength>
				<lp1:getetag>"{php printf('%xT-%xO', $stat['mtime'], $stat['size'])}"</lp1:getetag>
				<lp1:getlastmodified>{$stat.mtime|gmt}</lp1:getlastmodified>
				<lp2:executable>F</lp2:executable>
			</D:prop>
			<D:status>HTTP/1.1 200 OK</D:status>
		</D:propstat>
	</D:response>
{/if}
{/loop}
</D:multistatus>
