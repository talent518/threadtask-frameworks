<?php
namespace fwe\web;

class ResponseEvent {
	public $protocol;
	public $status;
	public $statusText;
	
	protected $isHeadSent = false;

	public $headers = ['Content-Type'=>'text/html; charset=utf-8','Task-Name'=>THREAD_TASK_NAME];
	public $isChunked = false;
	public $isEnd = false;
	public $isWebSocket = false;
	public $wsClass = null;

	/**
	 * @var RequestEvent
	 */
	protected $request;
	
	public function __construct(RequestEvent $request, string $protocol, int $status = 200, string $statusText = 'OK') {
		$this->request = $request;
		$this->protocol = $protocol;
		$this->status = $status;
		$this->statusText = $statusText;
		
		\Fwe::debug(get_called_class(), $this->protocol, false);
	}
	
	public function __destruct() {
		\Fwe::debug(get_called_class(), $this->protocol, true);
	}
	
	public function setStatus(int $status, ?string $statusText = null) {
		if($this->isHeadSent) return $this;

		$this->status = $status;
		if($statusText !== null) {
			$this->statusText = $statusText;
			return $this;
		}

		switch ($status) {
			// 消息编辑 播报
			// 这一类型的状态码，代表请求已被接受，需要继续处理。这类响应是临时响应，只包含状态行和某些可选的响应头信息，并以空行结束。由于 HTTP/1.0 协议中没有定义任何 1xx 状态码，所以除非在某些试验条件下，服务器禁止向此类客户端发送 1xx 响应。
		    case 100: $this->statusText = 'Continue'; break; // 客户端应当继续发送请求。这个临时响应是用来通知客户端它的部分请求已经被服务器接收，且仍未被拒绝。客户端应当继续发送请求的剩余部分，或者如果请求已经完成，忽略这个响应。服务器必须在请求完成后向客户端发送一个最终响应。
		    case 101: $this->statusText = 'Switching Protocols'; break; // 服务器已经理解了客户端的请求，并将通过Upgrade 消息头通知客户端采用不同的协议来完成这个请求。在发送完这个响应最后的空行后，服务器将会切换到在Upgrade 消息头中定义的那些协议。只有在切换新的协议更有好处的时候才应该采取类似措施。例如，切换到新的HTTP 版本比旧版本更有优势，或者切换到一个实时且同步的协议以传送利用此类特性的资源。
		    case 102: $this->statusText = 'Processing'; break; // 由WebDAV（RFC 2518）扩展的状态码，代表处理将被继续执行。

		    case 200: $this->statusText = 'OK'; break; // 请求已成功，请求所希望的响应头或数据体将随此响应返回。出现此状态码是表示正常状态。
		    case 201: $this->statusText = 'Created'; break; // 请求已经被实现，而且有一个新的资源已经依据请求的需要而建立，且其 URI 已经随Location 头信息返回。假如需要的资源无法及时建立的话，应当返回 '202 Accepted'。
		    case 202: $this->statusText = 'Accepted'; break; // 服务器已接受请求，但尚未处理。正如它可能被拒绝一样，最终该请求可能会也可能不会被执行。在异步操作的场合下，没有比发送这个状态码更方便的做法了。返回202状态码的响应的目的是允许服务器接受其他过程的请求（例如某个每天只执行一次的基于批处理的操作），而不必让客户端一直保持与服务器的连接直到批处理操作全部完成。在接受请求处理并返回202状态码的响应应当在返回的实体中包含一些指示处理当前状态的信息，以及指向处理状态监视器或状态预测的指针，以便用户能够估计操作是否已经完成。
		    case 203: $this->statusText = 'Non-Authoritative Information'; break; // 服务器已成功处理了请求，但返回的实体头部元信息不是在原始服务器上有效的确定集合，而是来自本地或者第三方的拷贝。当前的信息可能是原始版本的子集或者超集。例如，包含资源的元数据可能导致原始服务器知道元信息的超集。使用此状态码不是必须的，而且只有在响应不使用此状态码便会返回200 OK的情况下才是合适的。
		    case 204: $this->statusText = 'No Content'; break; // 服务器成功处理了请求，但不需要返回任何实体内容，并且希望返回更新了的元信息。响应可能通过实体头部的形式，返回新的或更新后的元信息。如果存在这些头部信息，则应当与所请求的变量相呼应。如果客户端是浏览器的话，那么用户浏览器应保留发送了该请求的页面，而不产生任何文档视图上的变化，即使按照规范新的或更新后的元信息应当被应用到用户浏览器活动视图中的文档。由于204响应被禁止包含任何消息体，因此它始终以消息头后的第一个空行结尾。
		    case 205: $this->statusText = 'Reset Content'; break; // 服务器成功处理了请求，且没有返回任何内容。但是与204响应不同，返回此状态码的响应要求请求者重置文档视图。该响应主要是被用于接受用户输入后，立即重置表单，以便用户能够轻松地开始另一次输入。与204响应一样，该响应也被禁止包含任何消息体，且以消息头后的第一个空行结束。
		    case 206: $this->statusText = 'Partial Content'; break; // 服务器已经成功处理了部分 GET 请求。类似于 FlashGet 或者迅雷这类的 HTTP下载工具都是使用此类响应实现断点续传或者将一个大文档分解为多个下载段同时下载。该请求必须包含 Range 头信息来指示客户端希望得到的内容范围，并且可能包含 If-Range 来作为请求条件。响应必须包含如下的头部域：Content-Range 用以指示本次响应中返回的内容的范围；如果是 Content-Type 为 multipart/byteranges 的多段下载，则每一 multipart 段中都应包含 Content-Range 域用以指示本段的内容范围。假如响应中包含 Content-Length，那么它的数值必须匹配它返回的内容范围的真实字节数。Date、ETag 和/或 Content-Location，假如同样的请求本应该返回200响应。Expires, Cache-Control，和/或 Vary，假如其值可能与之前相同变量的其他响应对应的值不同的话。假如本响应请求使用了 If-Range 强缓存验证，那么本次响应不应该包含其他实体头；假如本响应的请求使用了 If-Range 弱缓存验证，那么本次响应禁止包含其他实体头；这避免了缓存的实体内容和更新了的实体头信息之间的不一致。否则，本响应就应当包含所有本应该返回200响应中应当返回的所有实体头部域。假如 ETag 或 Last-Modified 头部不能精确匹配的话，则客户端缓存应禁止将206响应返回的内容与之前任何缓存过的内容组合在一起。
		    case 207: $this->statusText = 'Multi-Status'; break; // 由WebDAV(RFC 2518)扩展的状态码，代表之后的消息体将是一个XML消息，并且可能依照之前子请求数量的不同，包含一系列独立的响应代码。

			// 重定向
			// 这类状态码代表需要客户端采取进一步的操作才能完成请求。通常，这些状态码用来重定向，后续的请求地址（重定向目标）在本次响应的 Location 域中指明。
			// 当且仅当后续的请求所使用的方法是 GET 或者 HEAD 时，用户浏览器才可以在没有用户介入的情况下自动提交所需要的后续请求。客户端应当自动监测无限循环重定向（例如：A->A，或者A->B->C->A），因为这会导致服务器和客户端大量不必要的资源消耗。按照 HTTP/1.0 版规范的建议，浏览器不应自动访问超过5次的重定向。
		    case 300: $this->statusText = 'Multiple Choices'; break; // 被请求的资源有一系列可供选择的回馈信息，每个都有自己特定的地址和浏览器驱动的商议信息。用户或浏览器能够自行选择一个首选的地址进行重定向。除非这是一个 HEAD 请求，否则该响应应当包括一个资源特性及地址的列表的实体，以便用户或浏览器从中选择最合适的重定向地址。这个实体的格式由 Content-Type 定义的格式所决定。浏览器可能根据响应的格式以及浏览器自身能力，自动作出最合适的选择。当然，RFC 2616规范并没有规定这样的自动选择该如何进行。如果服务器本身已经有了首选的回馈选择，那么在 Location 中应当指明这个回馈的 URI；浏览器可能会将这个 Location 值作为自动重定向的地址。此外，除非额外指定，否则这个响应也是可缓存的。
		    case 301: $this->statusText = 'Moved Permanently'; break; // 被请求的资源已永久移动到新位置，并且将来任何对此资源的引用都应该使用本响应返回的若干个 URI 之一。如果可能，拥有链接编辑功能的客户端应当自动把请求的地址修改为从服务器反馈回来的地址。除非额外指定，否则这个响应也是可缓存的。新的永久性的URI 应当在响应的 Location 域中返回。除非这是一个 HEAD 请求，否则响应的实体中应当包含指向新的 URI 的超链接及简短说明。如果这不是一个 GET 或者 HEAD 请求，因此浏览器禁止自动进行重定向，除非得到用户的确认，因为请求的条件可能因此发生变化。注意：对于某些使用 HTTP/1.0 协议的浏览器，当它们发送的 POST 请求得到了一个301响应的话，接下来的重定向请求将会变成 GET 方式。
		    case 302: $this->statusText = 'Move Temporarily'; break; // 请求的资源临时从不同的 URI响应请求。由于这样的重定向是临时的，客户端应当继续向原有地址发送以后的请求。只有在Cache-Control或Expires中进行了指定的情况下，这个响应才是可缓存的。上文有提及。如果这不是一个 GET 或者 HEAD 请求，那么浏览器禁止自动进行重定向，除非得到用户的确认，因为请求的条件可能因此发生变化。注意：虽然RFC 1945和RFC 2068规范不允许客户端在重定向时改变请求的方法，但是很多现存的浏览器将302响应视作为303响应，并且使用 GET 方式访问在 Location 中规定的 URI，而无视原先请求的方法。状态码303和307被添加了进来，用以明确服务器期待客户端进行何种反应。
		    case 303: $this->statusText = 'See Other'; break; // 对应当前请求的响应可以在另一个 URL 上被找到，而且客户端应当采用 GET 的方式访问那个资源。这个方法的存在主要是为了允许由脚本激活的POST请求输出重定向到一个新的资源。这个新的 URI 不是原始资源的替代引用。同时，303响应禁止被缓存。当然，第二个请求（重定向）可能被缓存。注意：许多 HTTP/1.1 版以前的浏览器不能正确理解303状态。如果需要考虑与这些浏览器之间的互动，302状态码应该可以胜任，因为大多数的浏览器处理302响应时的方式恰恰就是上述规范要求客户端处理303响应时应当做的。
		    case 304: $this->statusText = 'Not Modified'; break; // 如果客户端发送了一个带条件的 GET 请求且该请求已被允许，而文档的内容（自上次访问以来或者根据请求的条件）并没有改变，则服务器应当返回这个状态码。304响应禁止包含消息体，因此始终以消息头后的第一个空行结尾。该响应必须包含以下的头信息：Date，除非这个服务器没有时钟。假如没有时钟的服务器也遵守这些规则，那么代理服务器以及客户端可以自行将 Date 字段添加到接收到的响应头中去（正如RFC 2068中规定的一样），缓存机制将会正常工作。ETag 和/或 Content-Location，假如同样的请求本应返回200响应。Expires, Cache-Control，和/或Vary，假如其值可能与之前相同变量的其他响应对应的值不同的话。假如本响应请求使用了强缓存验证，那么本次响应不应该包含其他实体头；否则（例如，某个带条件的 GET 请求使用了弱缓存验证），本次响应禁止包含其他实体头；这避免了缓存了的实体内容和更新了的实体头信息之间的不一致。假如某个304响应指明了当前某个实体没有缓存，那么缓存系统必须忽视这个响应，并且重复发送不包含限制条件的请求。假如接收到一个要求更新某个缓存条目的304响应，那么缓存系统必须更新整个条目以反映所有在响应中被更新的字段的值。
		    case 305: $this->statusText = 'Use Proxy'; break; // 被请求的资源必须通过指定的代理才能被访问。Location 域中将给出指定的代理所在的 URI 信息，接收者需要重复发送一个单独的请求，通过这个代理才能访问相应资源。只有原始服务器才能建立305响应。注意：RFC 2068中没有明确305响应是为了重定向一个单独的请求，而且只能被原始服务器建立。忽视这些限制可能导致严重的安全后果。
		    case 306: $this->statusText = 'Switch Proxy'; break; // 在最新版的规范中，306状态码已经不再被使用。
		    case 307: $this->statusText = 'Temporary Redirect'; break; // 请求的资源临时从不同的URI 响应请求。新的临时性的URI 应当在响应的 Location 域中返回。除非这是一个HEAD 请求，否则响应的实体中应当包含指向新的URI 的超链接及简短说明。因为部分浏览器不能识别307响应，因此需要添加上述必要信息以便用户能够理解并向新的 URI 发出访问请求。如果这不是一个GET 或者 HEAD 请求，那么浏览器禁止自动进行重定向，除非得到用户的确认，因为请求的条件可能因此发生变化。

			// 请求错误
			// 这类的状态码代表了客户端看起来可能发生了错误，妨碍了服务器的处理。除非响应的是一个 HEAD 请求，否则服务器就应该返回一个解释当前错误状况的实体，以及这是临时的还是永久性的状况。这些状态码适用于任何请求方法。浏览器应当向用户显示任何包含在此类错误响应中的实体内容。
			// 如果错误发生时客户端正在传送数据，那么使用TCP的服务器实现应当仔细确保在关闭客户端与服务器之间的连接之前，客户端已经收到了包含错误信息的数据包。如果客户端在收到错误信息后继续向服务器发送数据，服务器的TCP栈将向客户端发送一个重置数据包，以清除该客户端所有还未识别的输入缓冲，以免这些数据被服务器上的应用程序读取并干扰后者。
		    case 400: $this->statusText = 'Bad Request'; break; // 1、语义有误，当前请求无法被服务器理解。除非进行修改，否则客户端不应该重复提交这个请求。2、请求参数有误。
		    case 401: $this->statusText = 'Unauthorized'; break; // 当前请求需要用户验证。该响应必须包含一个适用于被请求资源的 WWW-Authenticate 信息头用以询问用户信息。客户端可以重复提交一个包含恰当的 Authorization 头信息的请求。如果当前请求已经包含了 Authorization 证书，那么401响应代表着服务器验证已经拒绝了那些证书。如果401响应包含了与前一个响应相同的身份验证询问，且浏览器已经至少尝试了一次验证，那么浏览器应当向用户展示响应中包含的实体信息，因为这个实体信息中可能包含了相关诊断信息。参见RFC 2617。
		    case 402: $this->statusText = 'Payment Required'; break; // 该状态码是为了将来可能的需求而预留的。
		    case 403: $this->statusText = 'Forbidden'; break; // 服务器已经理解请求，但是拒绝执行它。与401响应不同的是，身份验证并不能提供任何帮助，而且这个请求也不应该被重复提交。如果这不是一个 HEAD 请求，而且服务器希望能够讲清楚为何请求不能被执行，那么就应该在实体内描述拒绝的原因。当然服务器也可以返回一个404响应，假如它不希望让客户端获得任何信息。
		    case 404: $this->statusText = 'Not Found'; break; // 请求失败，请求所希望得到的资源未被在服务器上发现。没有信息能够告诉用户这个状况到底是暂时的还是永久的。假如服务器知道情况的话，应当使用410状态码来告知旧资源因为某些内部的配置机制问题，已经永久的不可用，而且没有任何可以跳转的地址。404这个状态码被广泛应用于当服务器不想揭示到底为何请求被拒绝或者没有其他适合的响应可用的情况下。出现这个错误的最有可能的原因是服务器端没有这个页面。
		    case 405: $this->statusText = 'Method Not Allowed'; break; // 请求行中指定的请求方法不能被用于请求相应的资源。该响应必须返回一个Allow 头信息用以表示出当前资源能够接受的请求方法的列表。鉴于 PUT，DELETE 方法会对服务器上的资源进行写操作，因而绝大部分的网页服务器都不支持或者在默认配置下不允许上述请求方法，对于此类请求均会返回405错误。
		    case 406: $this->statusText = 'Not Acceptable'; break; // 请求的资源的内容特性无法满足请求头中的条件，因而无法生成响应实体。除非这是一个 HEAD 请求，否则该响应就应当返回一个包含可以让用户或者浏览器从中选择最合适的实体特性以及地址列表的实体。实体的格式由 Content-Type 头中定义的媒体类型决定。浏览器可以根据格式及自身能力自行作出最佳选择。但是，规范中并没有定义任何作出此类自动选择的标准。
		    case 407: $this->statusText = 'Proxy Authentication Required'; break; // 与401响应类似，只不过客户端必须在代理服务器上进行身份验证。代理服务器必须返回一个 Proxy-Authenticate 用以进行身份询问。客户端可以返回一个 Proxy-Authorization 信息头用以验证。参见RFC 2617。
		    case 408: $this->statusText = 'Request Timeout'; break; // 请求超时。客户端没有在服务器预备等待的时间内完成一个请求的发送。客户端可以随时再次提交这一请求而无需进行任何更改。
		    case 409: $this->statusText = 'Conflict'; break; // 由于和被请求的资源的当前状态之间存在冲突，请求无法完成。这个代码只允许用在这样的情况下才能被使用：用户被认为能够解决冲突，并且会重新提交新的请求。该响应应当包含足够的信息以便用户发现冲突的源头。冲突通常发生于对 PUT 请求的处理中。例如，在采用版本检查的环境下，某次 PUT 提交的对特定资源的修改请求所附带的版本信息与之前的某个（第三方）请求向冲突，那么此时服务器就应该返回一个409错误，告知用户请求无法完成。此时，响应实体中很可能会包含两个冲突版本之间的差异比较，以便用户重新提交归并以后的新版本。
		    case 410: $this->statusText = 'Gone'; break; // 被请求的资源在服务器上已经不再可用，而且没有任何已知的转发地址。这样的状况应当被认为是永久性的。如果可能，拥有链接编辑功能的客户端应当在获得用户许可后删除所有指向这个地址的引用。如果服务器不知道或者无法确定这个状况是否是永久的，那么就应该使用404状态码。除非额外说明，否则这个响应是可缓存的。410响应的目的主要是帮助网站管理员维护网站，通知用户该资源已经不再可用，并且服务器拥有者希望所有指向这个资源的远端连接也被删除。这类事件在限时、增值服务中很普遍。同样，410响应也被用于通知客户端在当前服务器站点上，原本属于某个个人的资源已经不再可用。当然，是否需要把所有永久不可用的资源标记为'410 Gone'，以及是否需要保持此标记多长时间，完全取决于服务器拥有者。

		    case 411: $this->statusText = 'Length Required'; break; // 服务器拒绝在没有定义 Content-Length 头的情况下接受请求。在添加了表明请求消息体长度的有效 Content-Length 头之后，客户端可以再次提交该请求。
		    case 412: $this->statusText = 'Precondition Failed'; break; // 服务器在验证在请求的头字段中给出先决条件时，没能满足其中的一个或多个。这个状态码允许客户端在获取资源时在请求的元信息（请求头字段数据）中设置先决条件，以此避免该请求方法被应用到其希望的内容以外的资源上。
		    case 413: $this->statusText = 'Request Entity Too Large'; break; // 服务器拒绝处理当前请求，因为该请求提交的实体数据大小超过了服务器愿意或者能够处理的范围。此种情况下，服务器可以关闭连接以免客户端继续发送此请求。如果这个状况是临时的，服务器应当返回一个 Retry-After 的响应头，以告知客户端可以在多少时间以后重新尝试。
		    case 414: $this->statusText = 'Request-URI Too Long'; break; // 请求的URI 长度超过了服务器能够解释的长度，因此服务器拒绝对该请求提供服务。这比较少见，通常的情况包括：本应使用POST方法的表单提交变成了GET方法，导致查询字符串（Query String）过长。重定向URI “黑洞”，例如每次重定向把旧的 URI 作为新的 URI 的一部分，导致在若干次重定向后 URI 超长。客户端正在尝试利用某些服务器中存在的安全漏洞攻击服务器。这类服务器使用固定长度的缓冲读取或操作请求的 URI，当 GET 后的参数超过某个数值后，可能会产生缓冲区溢出，导致任意代码被执行[1]。没有此类漏洞的服务器，应当返回414状态码。
		    case 415: $this->statusText = 'Unsupported Media Type'; break; // 对于当前请求的方法和所请求的资源，请求中提交的实体并不是服务器中所支持的格式，因此请求被拒绝。
		    case 416: $this->statusText = 'Requested Range Not Satisfiable'; break; // 如果请求中包含了 Range 请求头，并且 Range 中指定的任何数据范围都与当前资源的可用范围不重合，同时请求中又没有定义 If-Range 请求头，那么服务器就应当返回416状态码。假如 Range 使用的是字节范围，那么这种情况就是指请求指定的所有数据范围的首字节位置都超过了当前资源的长度。服务器也应当在返回416状态码的同时，包含一个 Content-Range 实体头，用以指明当前资源的长度。这个响应也被禁止使用 multipart/byteranges 作为其 Content-Type。

		    case 417: $this->statusText = 'Expectation Failed'; break; // 在请求头 Expect 中指定的预期内容无法被服务器满足，或者这个服务器是一个代理服务器，它有明显的证据证明在当前路由的下一个节点上，Expect 的内容无法被满足。
		    case 418: $this->statusText = 'I\'m a teapot'; break; // 我是茶壶
		    case 421: $this->statusText = 'Misdirected Request'; break; // 请求被指向到无法生成响应的服务器（比如由于连接重复使用）
		    case 422: $this->statusText = 'Unprocessable Entity'; break; // 请求格式正确，但是由于含有语义错误，无法响应。（RFC 4918 WebDAV）
		    case 423: $this->statusText = 'Locked'; break; // 当前资源被锁定。（RFC 4918 WebDAV）
		    case 424: $this->statusText = 'Failed Dependency'; break; // 由于之前的某个请求发生的错误，导致当前请求失败，例如 PROPPATCH。（RFC 4918 WebDAV）
		    case 425: $this->statusText = 'Too Early'; break; // 状态码 425 Too Early 代表服务器不愿意冒风险来处理该请求，原因是处理该请求可能会被“重放”，从而造成潜在的重放攻击。（RFC 8470）
		    case 426: $this->statusText = 'Upgrade Required'; break; // 客户端应当切换到TLS/1.0。（RFC 2817）
		    case 449: $this->statusText = 'Retry With'; break; // 由微软扩展，代表请求应当在执行完适当的操作后进行重试。
		    case 451: $this->statusText = 'Unavailable For Legal Reasons'; break; // 该请求因法律原因不可用。（RFC 7725）

			// 服务器错误（5、6字头）
			// 这类状态码代表了服务器在处理请求的过程中有错误或者异常状态发生，也有可能是服务器意识到以当前的软硬件资源无法完成对请求的处理。除非这是一个HEAD 请求，否则服务器应当包含一个解释当前错误状态以及这个状况是临时的还是永久的解释信息实体。浏览器应当向用户展示任何在当前响应中被包含的实体。
			// 这些状态码适用于任何响应方法。
		    case 500: $this->statusText = 'Internal Server Error'; break; // 服务器遇到了一个未曾预料的状况，导致了它无法完成对请求的处理。一般来说，这个问题都会在服务器端的源代码出现错误时出现。
		    case 501: $this->statusText = 'Not Implemented'; break; // 服务器不支持当前请求所需要的某个功能。当服务器无法识别请求的方法，并且无法支持其对任何资源的请求。
		    case 502: $this->statusText = 'Bad Gateway'; break; // 作为网关或者代理工作的服务器尝试执行请求时，从上游服务器接收到无效的响应。
		    case 503: $this->statusText = 'Service Unavailable'; break; // 由于临时的服务器维护或者过载，服务器当前无法处理请求。这个状况是临时的，并且将在一段时间以后恢复。如果能够预计延迟时间，那么响应中可以包含一个 Retry-After 头用以标明这个延迟时间。如果没有给出这个 Retry-After 信息，那么客户端应当以处理500响应的方式处理它。注意：503状态码的存在并不意味着服务器在过载的时候必须使用它。某些服务器只不过是希望拒绝客户端的连接。
		    case 504: $this->statusText = 'Gateway Timeout'; break; // 作为网关或者代理工作的服务器尝试执行请求时，未能及时从上游服务器（URI标识出的服务器，例如HTTP、FTP、LDAP）或者辅助服务器（例如DNS）收到响应。注意：某些代理服务器在DNS查询超时时会返回400或者500错误
		    
		    case 505: $this->statusText = 'HTTP Version Not Supported'; break; // 服务器不支持，或者拒绝支持在请求中使用的 HTTP 版本。这暗示着服务器不能或不愿使用与客户端相同的版本。响应中应当包含一个描述了为何版本不被支持以及服务器支持哪些协议的实体。
		    case 506: $this->statusText = 'Variant Also Negotiates'; break; // 由《透明内容协商协议》（RFC 2295）扩展，代表服务器存在内部配置错误：被请求的协商变元资源被配置为在透明内容协商中使用自己，因此在一个协商处理中不是一个合适的重点。
		    case 507: $this->statusText = 'Insufficient Storage'; break; // 服务器无法存储完成请求所必须的内容。这个状况被认为是临时的。WebDAV (RFC 4918)
		    case 509: $this->statusText = 'Bandwidth Limit Exceeded'; break; // 服务器达到带宽限制。这不是一个官方的状态码，但是仍被广泛使用。
		    case 510: $this->statusText = 'Not Extended'; break; // 获取资源所需要的策略并没有被满足。
		    case 600: $this->statusText = 'Unparseable Response Headers'; break; // 源站没有返回响应头部，只返回实体内容。

		    default : $this->statusText = 'Unknown'; break;
		}
		
		return $this;
	}
	
	public function isHeadSent() {
		return $this->isHeadSent;
	}
	
	public function headSend(int $bodyLen = 0): bool {
		if($this->isHeadSent) return true;
		$this->isHeadSent = true;
		
		if($bodyLen >= 0) {
			$this->headers['Content-Length'] = $bodyLen;
			unset($this->headers['Transfer-Encoding']);
		} else {
			$this->headers['Transfer-Encoding'] = 'chunked';
			$this->isChunked = true;
			unset($this->headers['Content-Length']);
		}
		
		if($this->request->isKeepAlive) $this->headers['Keep-Alive'] = 'timeout=' . ceil($this->request->keepAlive - microtime(true));

		
		$this->headers['Date'] = gmdate('D, d-M-Y H:i:s T');
		
		ob_start();
		ob_implicit_flush(false);
		echo $this->protocol, ' ', $this->status, ' ', $this->statusText, "\r\n";
		foreach($this->headers as $name=>$value) {
			if(is_array($value)) {
				foreach($value as $val) {
					echo $name, ': ', (string) $val, "\r\n";
				}
			} else {
				echo $name, ': ', $value, "\r\n";
			}
		}
		echo "\r\n";
		$buf = ob_get_clean();
		
		return $this->send($buf);
	}
	
	public function setContentType(string $type) {
		$this->headers['Content-Type'] = $type;
		return $this;
	}
	
	public function setCookie(string $name, ?string $value, int $expires = 0, string $path = '', string $domain = '', bool $secure = false, bool $httponly = false, string $samesite = '') {
		$this->setRawCookie($name, $value === null ? null : urlencode($value), $expires, $path, $domain, $secure, $httponly, $samesite);
		return $this;
	}
	
	public function setRawCookie(string $name, ?string $value, int $expires = 0, string $path = '', string $domain = '', bool $secure = false, bool $httponly = false, string $samesite = '') {
		if($value === null || $value === '') {
			$expire = gmdate('D, d-M-Y H:i:s T', 1);
			$cookie = "$name=deleted; expires=$expire; Max-Age=0";
		} else {
			$cookie = "$name=$value";
			if($expires > 0) {
				$cookie .= '; expires=' . gmdate('D, d-M-Y H:i:s T', $expires);
				$diff = $expires - time();
				if($diff < 0) $diff = 0;
				$cookie .= "; Max-Age=$diff";
			}
		}
		if($path !== '') $cookie .= "; path=$path";
		if($domain !== '') $cookie .= "; domain=$domain";
		if($secure) $cookie .= "; secure";
		if($httponly) $cookie .= "; HttpOnly";
		if($samesite !== '') $cookie .= "; SameSite=$samesite";
		$this->headers['Set-Cookie'][] = $cookie;
		return $this;
	}
	
	public function write(string $data): bool {
		if(!$this->headSend(-1)) return false;
		
		$n = strlen($data);
		if(!$n || $this->request->method === 'HEAD') return true;

		if($this->isChunked) {
			$data = sprintf("%x\r\n%s\r\n", $n, $data);
		}
		
		return $this->send($data);
	}
	
	public function end(?string $data = null): bool {
		if($this->isEnd) {
			return true;
		}
		
		$this->isEnd = true;
		
		$n = strlen($data);
		if(!$this->headSend($n)) {
			return false;
		}
		if($this->request->method === 'HEAD') return true;
		
		if($this->isChunked) {
			if($n) {
				$data = sprintf("%x\r\n%s\r\n0\r\n\r\n", $n, $data);
			} else {
				$data = "0\r\n\r\n";
			}

			return $this->send($data);
		} elseif($n) {
			return $this->send($data);
		} else {
			return true;
		}
	}
	
	protected function send(string $data): bool {
		return $this->request->send($data);
	}
	
	/**
	 * @see RequestEvent::writeHandler()
	 */
	public function read() {
		if($this->fp) {
			if(feof($this->fp) || (!$this->size && !$this->rsize)) {
				\Fwe::$app->error('Send file is complete', 'web');

				$this->isEnd = true;
				fclose($this->fp);
				$this->fp = null;
				return false;
			}
			if($this->ranges) {
				$range = $this->ranges[$this->range];
				if(($buf = @fread($this->fp, min(16384, $this->rsize))) === false) {
					$this->isEnd = true;
					$this->request->isKeepAlive = false;
					fclose($this->fp);
					$this->fp = null;
					return false;
				}
				$n = strlen($buf);
				$this->size -= $n;
				$this->rsize -= $n;
				if(!$this->rsize) {
					$this->range++;
					if($this->range < count($this->ranges)) {
						$range = $this->ranges[$this->range];
						$buf .= "\r\n" . $range[2];
						$this->size -= strlen($range[2]);
						$this->rsize = $range[1] - $range[0] + 1;
						@fseek($this->fp, $range[0], SEEK_SET);
					} else {
						$this->isEnd = true;
						$this->size -= strlen($this->boundaryEnd);
						$buf .= "\r\n" . $this->boundaryEnd;
					}
				}
			} elseif(($buf = @fread($this->fp, min(16384, $this->size))) === false) {
				$n = strlen($buf);
				$this->size -= $n;
				$this->isEnd = true;
				$this->request->isKeepAlive = false;
				fclose($this->fp);
				$this->fp = null;
				return false;
			} else {
				$this->size -= strlen($buf);
				$this->isEnd = !$this->size;
			}
			return $buf;
		} else {
			return false;
		}
	}
	
	public static $transliteration = [
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE', 'Ç' => 'C',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ð' => 'D', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ő' => 'O',
        'Ø' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ű' => 'U', 'Ý' => 'Y', 'Þ' => 'TH',
        'ß' => 'ss',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ő' => 'o',
        'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ű' => 'u', 'ý' => 'y', 'þ' => 'th',
        'ÿ' => 'y',
    ];
	public function setContentDisposition(string $attachmentName, bool $isInline) {
		$disposition = ($isInline ? 'inline' : 'attachment');
        $fallbackName = str_replace(
            ['%', '/', '\\', '"'],
            ['_', '_', '_', '\\"'],
            strtr($attachmentName, static::$transliteration)
        );
        $utfName = rawurlencode(str_replace(['%', '/', '\\'], '', $attachmentName));

        $dispositionHeader = "{$disposition}; filename=\"{$fallbackName}\"";
        if ($utfName !== $fallbackName) {
            $dispositionHeader .= "; filename*=utf-8''{$utfName}";
        }
		$this->headers['Content-Disposition'] = $dispositionHeader;
		$this->headers['Content-Type'] = 'application/octet-stream';

		return $this;
	}
	
	protected $fp, $size = 0, $ranges = [], $range = 0, $rsize = 0, $boundaryEnd;
	public function sendFile($path) {
		if(is_file($path) && ($fp = @fopen($path, 'r')) !== false) {
			if(!isset($this->headers['Content-Disposition'])) {
				$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
				if(isset(static::$MIME_TYPES[$ext])) $this->setContentType(static::$MIME_TYPES[$ext]); else unset($this->headers['Content-Type']);
			}
			$stat = @fstat($fp);
			if($stat === false) {
				fclose($fp);
				
				goto end404;
			}
			if(!$stat['size']) {
				$this->end();
				return true;
			}
			$this->headers['Last-Modified'] = gmdate('D, d-M-Y H:i:s T', $stat['mtime']);
			$this->headers['ETag'] = sprintf('%xT-%xO', $stat['mtime'], $stat['size']);
			$this->headers['Accept-Ranges'] = 'bytes';
			$this->headers['Expires'] = gmdate('D, d-M-Y H:i:s T', time() + 3600);
			$this->headers['Cache-Control'] = ['must-revalidate', 'public', 'max-age=3600'];

			if(isset($this->request->headers['If-None-Match'])) {
				if($this->request->headers['If-None-Match'] === $this->headers['ETag']) {
					$this->headers['If-None-Match'] = 'false';
					$this->status = 304;
					$this->statusText = 'Not Modified';
					@fclose($fp);
					$this->end();
					return false;
				} else {
					$this->headers['If-None-Match'] = 'true';
				}
			}

			if(isset($this->request->headers['If-Modified-Since'])) {
				if($this->request->headers['If-Modified-Since'] === $this->headers['Last-Modified']) {
					$this->headers['If-Modified-Since'] = 'true';
					$this->status = 304;
					$this->statusText = 'Not Modified';
					@fclose($fp);
					$this->end();
					return false;
				} else {
					$this->headers['If-Modified-Since'] = 'false';
				}
			}

			if(isset($this->request->headers['If-Unmodified-Since'])) {
				if(strcmp($this->request->headers['If-Unmodified-Since'], $this->headers['Last-Modified']) < 0) {
					$this->headers['If-Unmodified-Since'] = 'false';
					$this->status = 412;
					$this->statusText = 'Precondition failed';
					@fclose($fp);
					$this->end();
					return false;
				} else {
					$this->headers['If-Unmodified-Since'] = 'true';
				}
			}

			if(isset($this->request->headers['If-Range'])) {
				if(isset($this->request->headers['Range']) && $this->request->headers['If-Range'] === $this->headers['Last-Modified']) {
					goto range;
				}
			} elseif(isset($this->request->headers['Range'])) {
				range:
				$ranges = [];
				$range = $this->request->headers['Range'];
				$matches = [];
				$n = preg_match_all('/(bytes=)?([0-9]+)\-([0-9]*),?\s*/i', $this->request->headers['Range'], $matches);

				if($n && implode('', $matches[0]) === $range) {
					for($i=0; $i<$n; $i++) {
						$range = $matches[3][$i];
						$start = $matches[2][$i];
						$end = ($range === '' ? $stat['size']-1 : $range);
						$ranges[] = [$start, $end];
						if($start < 0 || $start > $stat['size'] || $end < 0 || $end > $stat['size']) {
							$this->status = 416;
							$this->statusText = 'Requested Range Not Satisfiable';
							@fclose($fp);
							$this->end();
							return false;
						}
					}
					if($n > 1) {
						$boundary = bin2hex(random_bytes(8));
						$boundaryEnd = "--$boundary--\r\n";
						$contentType = $this->headers['Content-Type'];
						$this->status = 206;
						$this->statusText = 'Partial Content';
						$this->headers['Content-Type'] = 'multipart/byteranges; boundary=' . $boundary;
						$size = strlen($boundaryEnd);
						foreach($ranges as &$range) {
							$range[2] = "--$boundary\r\nContent-Type: {$contentType}\r\nContent-Range: bytes {$range[0]}-{$range[1]}/{$stat['size']}\r\n\r\n";
							$size += strlen($range[2]) + $range[1] - $range[0] + 3;
						}
						unset($range);
						$this->headSend($size);
						$this->size = $size;
						$this->ranges = $ranges;
						$this->fp = $fp;
						$this->boundaryEnd = $boundaryEnd;
						$this->send($ranges[0][2]);
						$this->rsize = $ranges[0][1] - $ranges[0][0] + 1;
						$this->size -= strlen($ranges[0][2]);
						@fseek($fp, $ranges[0][0], SEEK_SET);
						return true;
					} else {
						$size = $ranges[0][1] - $ranges[0][0] + 1;
						if($size === $stat['size']) goto all;
						$this->status = 206;
						$this->statusText = 'Partial Content';
						$this->headers['Content-Range'] = "bytes {$ranges[0][0]}-$ranges[0][1]/{$stat['size']}";
						$this->headSend($size);
						$this->fp = $fp;
						$this->size = $size;
						fseek($fp, $ranges[0][0], SEEK_SET);
						return true;
					}
				}
			}

		all:
			$this->headSend($stat['size']);
			$this->size = $stat['size'];
			$this->fp = $fp;
			return true;
		} else {
		end404:
			$this->status = 404;
			$this->statusText = 'Not Found';
			
			$this->end('<h1>Not Found</h1>');
			return false;
		}
	}

	public static $MIME_TYPES = [
		'md' => 'text/markdown',
		'sh' => 'text/plain',
		'txt' => 'text/plain',
		'htm' => 'text/html',
		'html' => 'text/html',
		'php' => 'text/plain; charset=utf-8',
		'css' => 'text/css',
		'js' => 'text/javascript',
		'json' => 'application/json',
		'xml' => 'application/xml',
		'swf' => 'application/x-shockwave-flash',
		'flv' => 'video/x-flv',
		'woff' => 'font/woff',
		'woff2' => 'font/woff2',
		
		// images
		'png' => 'image/png',
		'jpe' => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'jpg' => 'image/jpeg',
		'gif' => 'image/gif',
		'bmp' => 'image/bmp',
		'ico' => 'image/x-icon',
		'tiff' => 'image/tiff',
		'tif' => 'image/tiff',
		'svg' => 'image/svg+xml',
		'svgz' => 'image/svg+xml',
		
		// archives
		'zip' => 'application/zip',
		'rar' => 'application/x-rar-compressed',
		'exe' => 'application/x-ms-dos-executable',
		'msi' => 'application/x-msdownload',
		'cab' => 'application/vnd.ms-cab-compressed',
		'xz' => 'application/x-xz-compressed-tar',
		'gz' => 'application/x-compressed-tar',
		'bz2' => 'application/x-bzip-compressed-tar',
		'jar' => 'application/x-java-archive',
		'tgz' => 'application/x-compressed-tar',
		
		// audio/video
		'mp3' => 'audio/mpeg',
		'qt' => 'video/quicktime',
		'mov' => 'video/quicktime',
		
		// adobe
		'pdf' => 'application/pdf',
		'psd' => 'image/vnd.adobe.photoshop',
		'ai' => 'application/postscript',
		'eps' => 'application/postscript',
		'ps' => 'application/postscript',
		
		// ms office
		'doc' => 'application/msword',
		'rtf' => 'application/rtf',
		'xls' => 'application/vnd.ms-excel',
		'ppt' => 'application/vnd.ms-powerpoint',
		
		// open office
		'odt' => 'application/vnd.oasis.opendocument.text',
		'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
	];
	
	public function text(?string $str) {
		$this->setContentType('text/plain; charset=utf-8');
		$this->end($str);
		return $this;
	}
	
	public function json($data) {
		$this->setContentType('application/json; charset=utf-8');
		$this->end(json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
		return $this;
	}
	
	public function redirect(string $url, int $status = 302) {
		$this->headers['Location'] = $url;
		return $this->setStatus($status)->end();
	}
}
