require([
    "jquery",
    "mage/mage"
],function($) {
    $(document).ready(function() {
        $('#zmail-config-form').mage(
            'validation',
            { 
                submitHandler: function(form) {
					event.preventDefault();
					$self = $('[name=submit]');
					$data = $('#zmail-config-form').serialize()+"&form_key="+$("[name=form_key]").val();
					
                    $.ajax({
                        url: $(this).attr("action"),
                        data: $data,
                        type: 'POST',
                        dataType: 'json',
                        beforeSend: function() {
							if($self.find(".loading-spinner").length === 0){
								$self.append($('<div>').addClass("loading-spinner"));
							}else {
								xhr.abort();
								return;
							}
							$('label.zohomail_error').remove();
                            $('.zohomail_error').removeClass('zohomail_error');
                        },
                        success: function(data, status, xhr) {
							$self.find(".loading-spinner").remove();
							if(data.result === 'success'){
								addZeptoSuccessMessage('Plugin configured successfully');
								$('#zohomail_test_btn').removeClass('zmail-dispNone');
								$self.addClass('zmail-dispNone');
								$('#zmail_modify_config').removeClass('zmail-dispNone');
								$('#zohomail_mailconfig_btn').attr('zmail-dispNone');
								$("[name=zmail_ident_general]").attr("disabled","disabled");
								$("[name=zmail_ident_support]").attr("disabled","disabled");
								$("[name=zmail_ident_sales]").attr("disabled","disabled");
								$("[name=zmail_ident_custom1]").attr("disabled","disabled");
								$("[name=zmail_ident_custom2]").attr("disabled","disabled");
								window.scrollTo(0,0);
							}
							else {
								if(data.hasOwnProperty('error_message')){
									addZeptoErrorMessage(data['error_message']);
									window.scrollTo(0,0);
								}
								else if(data.hasOwnProperty('email_error')){
									$email_error = data.email_error;
									$.each($email_error,function(index,item){
										
										$label = $('<label>').attr("id",item['type']+"-error").addClass("zohomail_error").attr("for",item['type']).html(item['error']['message']);
										$('#'+item['type']).addClass('zohomail_error');
										$label.insertAfter($('[name='+item['type']+']'));
										
									});
									
								}
							}
                            
                        }
                    });
                }
            }
        );
		
		$("#zohomail_authorize_btn").on('click', function(){
			$self = $(this);
			$data = {};
			$data.clientId = $("#zohomail_client_id").val();
			$data.clientSecret = $("#zohomail_client_secret").val();
			$data.options = "authorize";
			$data.form_key =$("[name=form_key]").val();
			$data.domain = $("[name=zohomail_domain]").val();
			$.ajax({
				url: $(this).attr("action"),
				data: $data,
				type: 'POST',
				dataType: 'json',
				beforeSend: function() {
					if($self.find(".loading-spinner").length === 0){
						$self.append($('<div>').addClass("loading-spinner"));
					}else {
						xhr.abort();
						return;
					}
					$('label.zohomail_error').remove();
					$('.zohomail_error').removeClass('zohomail_error');
				},
				success: function(data, status, xhr) {
					$self.find(".loading-spinner").remove();
					if(data.result === 'success'){
						
						newWindow = window.open(data.authorize_url,'Zoho Mail','width=400,height=400');
						window.addEventListener('message', function(event) {
							$result = event.data;
							if($result.result === 'success'){
								window.location.reload();
							} else{
								addZeptoErrorMessage('Invalid client secret');
								window.removeEventListener('message',function(event){});
							}
							
						});
						window.scrollTo(0,0);
					}
					else {
						if(data.hasOwnProperty('error_message')){
							addZeptoErrorMessage(data['error_message']);
							window.scrollTo(0,0);
						}
						else if(data.hasOwnProperty('email_error')){
							$email_error = data.email_error;
							$.each($email_error,function(index,item){
								$error_data = item['error']['data'];
								$error_msg = '';
								console.log($error_data['moreInfo']);
								if($error_data['moreInfo']) {
									$error_msg = $error_data['moreInfo'];
								}
								else if($error_data['errorCode']) {
									$error_msg = $error_data['errorCode'];
								}
								$label = $('<label>').attr("id",item['type']+"-error").addClass("zohomail_error").attr("for",item['type']).html($error_msg);
								$('#'+item['type']).addClass('zohomail_error');
								$label.insertAfter($('[name='+item['type']+']'));
								
							});
							
						}
					}
					
				}
			});
			return false;
		});
		$('[purpose="configure-accordion"]').click(function(e){
			var $self = $(this);
			
			var $activeForm = $self.parent().siblings(".accordion-body");
			if(!$self.closest('[purpose=accordion-box]').hasClass('zmail-accordion-disabled')){
				$.each($activeForm,function(index,obj){
					if($(obj).attr("is_configured") === 1){
						$(obj).find('[purpose=configure-accordion]').addClass("zmailaccordion__trigger--configured");
					}
				});
				
				$self.removeClass("zmailaccordion__trigger--collapsed  zmailaccordion__trigger--configured").addClass("zmailaccordion__trigger--expanded");
				$self.parent().siblings(".accordion-body").removeClass("zmail-accordion-active").addClass("zmail-accordion-inactive");
				$self.parent(".accordion-body").removeClass("zmail-accordion-inactive").addClass("zmail-accordion-active");
			}
			
		});
		$('[purpose=reauthorize]').click(function(e) {
			e.preventDefault();
			$("[name=zohomail_domain]").removeAttr("disabled");
			$("#zohomail_client_id").removeAttr("disabled");
			$("#zohomail_client_secret").removeAttr("disabled");
			$("#zohomail_authorize_btn").removeClass("zmail-dispNone");
			$(this).addClass("zmail-dispNone");
		});
		$('[purpose=reconfigure]').click(function(e) {
			e.preventDefault();
			$("[name=zmail_ident_general]").removeAttr("disabled");
			$("[name=zmail_ident_support]").removeAttr("disabled");
			$("[name=zmail_ident_sales]").removeAttr("disabled");
			$("[name=zmail_ident_custom1]").removeAttr("disabled");
			$("[name=zmail_ident_custom2]").removeAttr("disabled");
			
			$('#zmail_modify_config').addClass('zmail-dispNone');
			$('#zohomail_test_btn').addClass('zmail-dispNone');
			$('#zohomail_mailconfig_btn').removeClass('zmail-dispNone');
		});
		$('[purpose=copyredirecturi]').click(function(e) {
            var copyText = document.getElementById('zmail_redirection_url');
            		copyText.select();
            		copyText.setSelectionRange(0, copyText.value.length);
            		document.execCommand('copy');
      });
	  $("#zohomail_test_btn").on('click', function(){
		$self = $(this);
		$.ajax({
			url: $(this).attr("action"),
			data: {
				'options' : 'saveEmailSettings',
				'form_key' : $('[name=form_key]').val(),
				'zmail_ident_general':$('[name=zmail_ident_general]').val(),
				'zmail_ident_support':$('[name=zmail_ident_support]').val(),
				'zmail_ident_sales':$('[name=zmail_ident_sales]').val(),
				'zmail_ident_custom1':$('[name=zmail_ident_custom1]').val(),
				'zmail_ident_custom2':$('[name=zmail_ident_custom2]').val(),
			},
			type: 'POST',
			dataType: 'json',
			beforeSend: function() {
				if($self.find(".loading-spinner").length === 0){
					$self.append($('<div>').addClass("loading-spinner"));
				}else {
					xhr.abort();
					return;
				}
				$('label.zohomail_error').remove();
				$('.zohomail_error').removeClass('zohomail_error');
			},
			success: function(data, status, xhr) {
				$self.find(".loading-spinner").remove();
				if(data.result == 'success'){
					addZeptoSuccessMessage('Configuration working fine');
					$('[purpose=zmail_modify_config]').removeClass('zmail-dispNone');
				}
				else {
					if(data.hasOwnProperty('error_message')){
						addZeptoErrorMessage(data['error_message']);
					}
					else if(data.hasOwnProperty('email_error')){
						$email_error = data.email_error;
						$.each($email_error,function(index,item){
									$error_data = item['error']['data'];
									$error_msg = '';
									if($error_data['moreInfo']) {
										$error_msg = $error_data['moreInfo'];
									}
									else if($error_data['errorCode']) {
										$error_msg = $error_data['errorCode'];
									}
									$label = $('<label>').attr("id",item['type']+"-error").addClass("zohomail_error").attr("for",item['type']).html($error_msg);
									$('#'+item['type']).addClass('zohomail_error');
									$label.insertAfter($('[name='+item['type']+']'));
						});
							
					}
				}
						
			}
		});
	});

	  $("#zohomail_test_btn").on('click', function(){
		$self = $(this);
		$.ajax({
			url: $(this).attr("action"),
			data: {
				'options' : 'testOauthSettings',
				'form_key' : $('[name=form_key]').val()
			},
			type: 'POST',
			dataType: 'json',
			beforeSend: function() {
				if($self.find(".loading-spinner").length === 0){
					$self.append($('<div>').addClass("loading-spinner"));
				}else {
					xhr.abort();
					return;
				}
				$('label.zohomail_error').remove();
				$('.zohomail_error').removeClass('zohomail_error');
			},
			success: function(data, status, xhr) {
				$self.find(".loading-spinner").remove();
				if(data.result == 'success'){
					addZeptoSuccessMessage('Configuration working fine');
					$('[purpose=zmail_modify_config]').removeClass('zmail-dispNone');
				}
				else {
					if(data.hasOwnProperty('error_message')){
						addZeptoErrorMessage(data['error_message']);
					}
					else if(data.hasOwnProperty('email_error')){
						$email_error = data.email_error;
						$.each($email_error,function(index,item){
									$error_data = item['error']['data'];
									$error_msg = '';
									if($error_data['moreInfo']) {
										$error_msg = $error_data['moreInfo'];
									}
									else if($error_data['errorCode']) {
										$error_msg = $error_data['errorCode'];
									}
									$label = $('<label>').attr("id",item['type']+"-error").addClass("zohomail_error").attr("for",item['type']).html($error_msg);
									$('#'+item['type']).addClass('zohomail_error');
									$label.insertAfter($('[name='+item['type']+']'));
						});
							
					}
				}
						
			}
		});
	});
	$("[purpose=zmail_client_help]").click(function(){
		$domain = $("[name=zohomail_domain]");
		$url = 'https://www.zoho.com/accounts/protocol/oauth-setup.html';
		window.open($url, '_blank').focus();
	});
	$("[purpose=zmail_generate_client]").click(function(){
		$domain = $("[name=zohomail_domain]").val();
		$url = 'https://api-console.'+ $domain + '/add#web';
		window.open($url, '_blank').focus();
	});
    });
	
});
