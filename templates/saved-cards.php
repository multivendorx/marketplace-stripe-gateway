<?php global $WCMp_Stripe_Gateway; ?>
<h2 id="saved-cards" class="saved_card_title" >
	<?php echo __( 'Saved cards', $WCMp_Stripe_Gateway->text_domain ); ?>
</h2>
<table class="shop_table" id="saved-cards-table">
	<thead>
		<tr>
			<th><?php echo __( 'Card', $WCMp_Stripe_Gateway->text_domain ); ?></th>
			<th><?php echo __( 'Expires', $WCMp_Stripe_Gateway->text_domain ); ?></th>
			<th></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $cards as $card ) : ?>
		<tr>
			<td><?php printf( __( '%s card ending in %s', $WCMp_Stripe_Gateway->text_domain ), ( isset( $card->type ) ? $card->type : $card->brand ), $card->last4 ); ?></td>
			<td><?php printf( __( 'Expires %s/%s', $WCMp_Stripe_Gateway->text_domain ), $card->exp_month, $card->exp_year ); ?></td>
			<td>
				<form action="" method="POST">
					<?php wp_nonce_field ( 'stripe_del_card' ); ?>
					<input type="hidden" name="stripe_delete_card" value="<?php echo esc_attr( $card->id ); ?>">
					<input type="submit" class="button" value="<?php _e( 'Delete card', $WCMp_Stripe_Gateway->text_domain ); ?>">
				</form>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
