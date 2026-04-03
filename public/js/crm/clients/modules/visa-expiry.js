/**
 * Visa Expiry module - Visa expiry notifications, polling, sound alert
 * Extracted from detail-main.js - Phase 3h refactoring.
 * Requires: jQuery, ClientDetailConfig, iziToast (optional)
 */
(function($) {
    'use strict';
    if (!$) return;

    var playing = false;
    var $soundBtn = null;

    function load_visa_expiry_messages(client_id) {
        $.ajax({
            url: window.ClientDetailConfig.urls.fetchVisaExpiryMessages,
            method: "GET",
            data: { client_id: client_id },
            success: function(data) {
                if (data != 0 && data !== '0') {
                    if (typeof iziToast !== 'undefined') {
                        iziToast.show({
                            backgroundColor: 'rgba(0,0,255,0.3)',
                            messageColor: 'rgba(255,255,255)',
                            title: '',
                            message: data,
                            position: 'bottomRight'
                        });
                    }

                    var player = document.getElementById('player');
                    if (player) {
                        if (!playing) {
                            try {
                                player.play();
                                playing = true;
                                if ($soundBtn && $soundBtn.length) $soundBtn.text('stop sound').addClass('down');
                            } catch (e) {
                                console.warn('Visa expiry: could not play sound', e);
                            }
                        } else {
                            player.pause();
                            playing = false;
                            if ($soundBtn && $soundBtn.length) $soundBtn.text('restart sound').removeClass('down');
                        }
                    }
                }
            }
        });
    }

    $(document).ready(function() {
        $soundBtn = $('#button');
        $soundBtn.on('click.visaExpiry', function() {
            var player = document.getElementById('player');
            if (!player) return;
            playing = !playing;
            if (playing) {
                try { player.play(); } catch (e) {}
                $soundBtn.text('stop sound').addClass('down');
            } else {
                player.pause();
                $soundBtn.text('restart sound').removeClass('down');
            }
        });

        setInterval(function() {
            var client_id = window.ClientDetailConfig.clientId;
            if (client_id) load_visa_expiry_messages(client_id);
        }, 900000); // 15 min interval
    });
})(typeof jQuery !== 'undefined' ? jQuery : null);
