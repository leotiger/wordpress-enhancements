<?php

defined( 'ABSPATH' ) or die( "you do not have acces to this page!" );

/**
 * 1. Make sure you use the Font-Awesome 5 Library. A free plugin with the same name is available for download.
 * 2. Do NOT hide the current Manage Consent Tab under settings.
 * 3. Change CSS if so desired!
 */

function myCustomManageConsent() {
	?>
	<div id="manageconsent" class="cmplz-show-banner">Manage consent</div>
	<style>
        #manageconsent {
            font-family: "Chalkduster";
            position: fixed;
            cursor: pointer;
            width: 180px;
            height: 30px;
			
            bottom: 200px;
            right: -4.7rem;
            font-size: 1rem;
			vertical-align: middle;
            background-color: var(--wp--preset--color--foreground);
            color: #ddd;
            line-height: 2;
            border-radius: 10px 10px 0 0;
            text-align: center;
            /* box-shadow: 2px 2px 3px #999; */
			transform: rotate(-90deg);
			mix-blend-mode: difference;
        }

        #manageconsent:hover {
            background-color: #333;
            color: #fff;
        }
	    .cmplz-manage-consent {display:none;}
	</style>
	<script>
		document.querySelector("#manageconsent").addEventListener("click", function() {
            document.querySelectorAll('.cmplz-manage-consent').forEach(obj => {
                obj.click();

            });
		});
			
	</script>

	<?php

}

add_action( 'wp_footer', 'myCustomManageConsent' );