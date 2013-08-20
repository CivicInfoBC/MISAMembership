/**
 *	Attaches a callback to a particular event on
 *	a particular object while preserving the existing
 *	callbacks installed thereupon.
 *
 *	\param [in] obj
 *		The object to attach to.
 *	\param [in] event
 *		The event on \em obj to attach to.
 *	\param [in] func
 *		The callback to attach.
 */
function AddCallbackToEvent (obj, event, func) {

	if (typeof obj[event]!='function') obj[event]=func;
	else {
	
		var old_event=obj[event];
	
		obj[event]=function () {
		
			if (old_event) old_event();
			
			return func();
		
		}
	
	}

}


if (typeof RegExp.escape!='function') RegExp.escape=function (str) {

	return str.replace(new RegExp('\\(\\.\\*\\+\\?\\|\\(\\)\\[\\]\\{\\}\\)\\^\\$\\\\','g'),'\\$&');

}


if (!String.prototype.trim) String.prototype.trim=function () {

	return this.replace(/^\s+|\s+$/g,'');

};


/**
 *	Adds a CSS class to a given object.
 *
 *	\param [in] obj
 *		The object to add the CSS class to.
 *	\param [in] class_name
 *		The name of the class to add.
 */
function AddClass (obj, class_name) {

	obj.className=obj.className+' '+class_name;

}


/**
 *	Removes a CSS class from a given object.
 *
 *	\param [in] obj
 *		The object to remove the CSS class from.
 *	\param [in] class_name
 *		The name of the class to remove.
 */
function RemoveClass (obj, class_name) {

	var regex=new RegExp(RegExp.escape(class_name));

	obj.className=obj.className.replace(regex,'');

}


function ErrorElement (obj) {

	//	Race up the DOM
	while (obj.parentNode.tagName!=='FORM') obj=obj.parentNode;
	
	//	Recurse back down
	error_element(obj);

}


function error_element (obj) {

	if (typeof obj.className==='undefined') return;

	AddClass(obj,'error');

	for (obj=obj.firstChild;obj!==null;obj=obj.nextSibling) error_element(obj);

}


function UnerrorElement (obj) {

	//	Race up the DOM
	while (obj.parentNode.tagName!=='FORM') obj=obj.parentNode;
	
	//	Recurse back down
	unerror_element(obj);

}


function unerror_element (obj) {

	if (typeof obj.className==='undefined') return;

	RemoveClass(obj,'error');
	
	for (obj=obj.firstChild;obj!==null;obj=obj.nextSibling) unerror_element(obj);

}


function IsNumeric (n) {

	return !isNaN(parseFloat(n)) && isFinite(n);

}