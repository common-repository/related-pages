//zmazanie url
function deleteURL(uri)
	{
		var conf = confirm("Are you sure you want to delete " + uri + " ?");
		if(conf == true)
			{
				return true;
				//alert("OK... you chose to proceed with deletion of ");
			}
				else
					return false;
	}

//validacia formulara
function validuj_formular(val1,inp)
	{
		var linka = document.getElementById('mod_url' + val1).value;
		var form1 = 'tosomja' + val1;

		if(linka.replace(/\s/g,"") == "")
			{
				alert('URL is empty!');
				return false;
			}
				else
					{
						//alert(inp);
						//document.getElementById('tosomja' + inp).submit();
						return true;
					}
	}

//modifikacia url
function modifyURL(inp,inp1,val1,val_id,c_ciel)
	{
		if(existujuce[inp]!=1)
			{
				for(var j=1;j<=max_iO;j++)
					{
						tmp_inp                                        = 'tbrow_' + j + '_mod';
						document.getElementById(tmp_inp).style.display = 'none';
						existujuce[tmp_inp]                            = 0;
					}
				//ziskame hodnoty objektov
				ziskaj_1                                  = 'row' + val1 + '_1';
				ziskaj_2                                  = 'row' + val1 + '_2';
				ziskaj_3                                  = 'row' + val1 + '_3';
				ziskaj_4                                  = 'row' + val1 + '_4';
				ziskaj_5                                  = 'row' + val1 + '_5';
				//alert(ziskaj_1);

				//spravime is url na formular
				var myurl       = document.URL;
				if(myurl[urllgt]=="#")
					var urllgt = myurl.length - 1;
				var form_action = document.URL.substr(0,urllgt);
				var pole_ciel   = new Array;
				pole_ciel[c_ciel] = ' selected=\'selected\' ';
				//select box pre vyber ciela
				var selectbox   = '<select name=\'mod_ciel\'><option value=\'default\' '+pole_ciel['default']+'>default</option><option value=\'_blank\' '+pole_ciel['_blank']+'>_blank</option><option value=\'_self\' '+pole_ciel['_self']+'>_self</option><option value=\'_parent\' '+pole_ciel['_parent']+'>_parent</option><option value=\'_top\' '+pole_ciel['_top']+'>_top</option></select>';
				//<input type=\'text\' name=\'mod_ciel\' value=\''  + document.getElementById(ziskaj_2).innerHTML.replace(/'/g, "&#039;") + '\' placeholder=\'Target of URL goes here\' />
				document.getElementById(inp).style.display = 'table-cell';
				document.getElementById(inp1).style.display = 'table-row';
				document.getElementById(inp).innerHTML     = '<form action=\'' + form_action + '\' enctype=\'multipart/form-data\' method=\'post\' id=\'tosomja' + val1 + '\' class=\'mod_form\'><table class=\'hovertable_child\'><tr><td style="padding:2ex;margin:0;">URL<sup title=\'mandatory field\'>(*)</sup>: <input type=\'text\' name=\'mod_url\' value=\'' + document.getElementById(ziskaj_1).innerHTML.replace(/'/g, "&#039;") + '\' id=\'mod_url' + val_id + '\' placeholder=\'Place for URL\' autofocus size=\'75\' /></td></tr><tr><td style="padding:2ex;margin:0;">Anchor text<sup title=\'mandatory field\'>(*)</sup>: <input type=\'text\' name=\'mod_name\' value=\'' + document.getElementById(ziskaj_5).innerHTML.replace(/'/g, "&#039;") + '\' id=\'mod_name' + val_id + '\' placeholder=\'Place for anchor text\' required size=\'50\' /></td></tr><tr><td style=\'padding:2ex;margin:0;\'>Target: ' + selectbox + '</td></tr><tr><td style=\'padding:2ex;margin:0;\'>CSS Class for the link: <input type=\'text\' name=\'mod_trieda\' value=\''  + document.getElementById(ziskaj_3).innerHTML.replace(/'/g, "&#039;") + '\' placeholder=\'CSS class\'/></td></tr><tr><td style=\'padding:2ex;margin:0;\'>Attribute of link: <input type=\'text\' name=\'mod_attr\' value=\''  + document.getElementById(ziskaj_4).innerHTML.replace(/'/g, "&#039;") + '\' placeholder=\'Attribute goes here\' /></td></tr><tr><td colspan=\'2\'><input type=\'hidden\' name=\'mod_id\' value=\'' + val_id + '\' /><input type=\'submit\' value=\'Modify this record now!\' onclick=\'return validuj_formular(' + val_id + ',' + val1 + ');\' /></td></tr></table></form>';
				//alert('ok');
				existujuce[inp] = 1;
			}
		return true;
	}

//este si zapamatame, co sa kde klika - pre modifikaciu
var existujuce=new Array;

//strpos funkcia
function strpos (haystack, needle, offset)
	{
		var i = (haystack + '').indexOf(needle, (offset || 0));
		return i === -1 ? false : i;
	}

//funkcia na otvorenie/zatvorenie okna
function showhide(inp)
	{
		var obj = document.getElementById(inp);
		if( (obj.style.display == 'block') || (obj.style.display == '') )
			{
				document.getElementById(inp).style.display = 'none';
				createCookie('style'+inp,'none',30);
			}
				else
					{
						document.getElementById(inp).style.display = 'block';
						createCookie('style'+inp,'block',30);
					}
		return true;
	}

//oznacime aktivne zotriedenie
zotriedenie = 1;

elementid = 'ord' + zotriedenie;

document.getElementById(elementid).style.color = '#FF0A0A';
