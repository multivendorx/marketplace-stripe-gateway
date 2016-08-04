jQuery(document).ready(function($) {
	$("#reset_card_data_stripe_id").click(function(e){
		var result = confirm("do you realy want to reset data");
		var user_id = $(this).attr("data-element");
		if(result){
			var data = {
				action : 'reset_all_card_stripe',
				user_id : user_id					
			}
			$.post(wcmp_stripe.ajaxurl, data, function(response) {
				if(response) {
					$("#reset-stripe").remove();
					$("#reset-stripe-table").remove();
					$("#saved-cards").remove();
					$("#saved-cards-table").remove();
					$(".myaccount_user").prepend( "<center><p>All card reset successful</p></center>" );
				}
				else {
					$(".myaccount_user").prepend( "<center><p>sorry error in proccess</p></center>" );					
				}
			});			
		}
		else {
			return result;
		}
	});		
});