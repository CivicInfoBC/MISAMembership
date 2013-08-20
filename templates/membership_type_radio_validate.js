var membership_type=false;
var membership_type_id=document.getElementById('form').elements['membership_type_id'];
for (var i=0;i<membership_type_id.length;++i) {

	if (membership_type_id[i].checked) {
	
		membership_type=true;
		
		break;
	
	}

}
if (membership_type) {

	for (var i=0;i<membership_type_id.length;++i) UnerrorElement(membership_type_id[i]);

} else {

	for (var i=0;i<membership_type_id.length;++i) ErrorElement(membership_type_id[i]);
	verified=false;

}