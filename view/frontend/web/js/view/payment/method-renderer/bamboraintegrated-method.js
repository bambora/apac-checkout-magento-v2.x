/**
 * @author    Reign <hello@reign.com.au>
 * @version   1.1.0
 * @copyright Copyright (c) 2018 Reign. All rights reserved.
 * @copyright Copyright (c) 2018 Bambora. All rights reserved.
 * @license   Proprietary/Closed Source
 * By viewing, using, or actively developing this application in any way, you are
 * henceforth bound the license agreement, and all of its changes, set forth by
 * Reign and Bambora. The license can be found, in its entirety, at this address:
 * http://www.reign.com.au/magento-licence
 */
/*browser:true*/
/*global define*/
define(
    [
        'Magento_Payment/js/view/payment/cc-form',
        'jquery',
        'mage/url',
        'Magento_Payment/js/model/credit-card-validation/validator',
    ],
    function (Component, $, url) {
        'use strict';
        var linkUrl = url.build('bamboraintegrated/index/index'); 
        return Component.extend({
            defaults: {
                template: 'Bambora_Apaccheckout/payment/bamboraintegrated-form'
            },

            getCode: function() {
                return 'bambora_apaccheckout';
            },

            isActive: function() {
                return true;
            },

            validate: function() {
                var element;
                element = '<script>function removeOverlay() {jQuery(".bambora-overlay").remove()}</script><div class="bambora-overlay" style="position: absolute; left: 0px; top: 0px; display: table-cell; text-align: center; vertical-align: middle; background: rgba(0, 0, 0, 0.75); z-index: 10000; height: 1695px; width: 100%;"><iframe id="bambora-iframe" src="' + linkUrl + '?email='+jQuery("#customer-email").val()+'" style="background-image: url(http://bambora.reign.net.au/m1933ce/skin/frontend/base/default/images/spinner.gif); background-repeat: no-repeat; background-position: 50% 50%; padding: 0px; border: none; z-index: 999999; background-color: rgb(255, 255, 255);background-repeat: no-repeat; background-position: 50% 50%; width: 400px; min-width: 320px; height: 704px; margin: 35px auto 0px; display: table-row; background-size: 25%; text-align: center; box-shadow: rgb(0, 0, 0) 0px 0px 70px 0px;"></iframe></div>';
                jQuery("body").append(element);
                
                jQuery("body").on("click",".bambora-overlay",function(){
                    jQuery(this).remove();
                })

                return false;
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            }
        });
    }
);
