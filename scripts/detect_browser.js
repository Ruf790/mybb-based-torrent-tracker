function TSDetectBrowser()
{
	var useragent = navigator.userAgent.toLowerCase();
	var useragent_version = parseInt(navigator.appVersion);
	var browser = "unknown";

	if(navigator.product == "Gecko" && navigator.vendor.indexOf("Apple Computer") != -1)
	{
		browser = "safari";
	}
	else if(navigator.product == "Gecko")
	{
		browser = "mozilla";
	}
	else if(useragent.indexOf("opera") != -1)
	{
		browser = "opera";
	}
	else if(useragent.indexOf("konqueror") != -1)
	{
		browser = "konqueror";
	}
	else if(useragent.indexOf("msie") != -1)
	{
		browser = "ie";
	}
	else if(useragent.indexOf("compatible") == -1 && useragent.indexOf("mozilla") != -1)
	{
		browser = "netscape";
	}

	return browser;
}