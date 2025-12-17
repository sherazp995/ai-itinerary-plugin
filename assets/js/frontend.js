/**
 * AI Itinerary Plugin - Frontend JavaScript
 */

(function($) {
    'use strict';
    
    const AIPWidget = {
        currentItinerary: null,
        currentItineraryId: null,
        hasUnsavedChanges: false,
        
        init: function() {
            this.bindEvents();
            this.initGoogleAuth();
            this.checkUserLimit();
        },
        
        bindEvents: function() {
            // Trigger button
            $('.aip-trigger-btn').on('click', this.toggleWidget.bind(this));
            $('.aip-close-btn').on('click', this.closeWidget.bind(this));
            
            // Auth tabs
            $('.aip-auth-tab').on('click', this.switchAuthTab.bind(this));
            
            // Forms
            $('.aip-login-form').on('submit', this.handleLogin.bind(this));
            $('.aip-register-form').on('submit', this.handleRegister.bind(this));
            $('.aip-continue-guest').on('click', this.continueAsGuest.bind(this));
            
            // Interface toggle
            $('.aip-toggle-chat').on('click', () => this.toggleInterface('chat'));
            $('.aip-toggle-form').on('click', () => this.toggleInterface('form'));
            
            // Chat
            $('.aip-send-btn').on('click', this.handleChatSend.bind(this));
            $('.aip-chat-field').on('keypress', (e) => {
                if (e.which === 13) this.handleChatSend(e);
            });
            
            // Form
            $('.aip-itinerary-form').on('submit', this.handleFormSubmit.bind(this));
            
            // Results actions
            $('.aip-btn-download').on('click', this.downloadPDF.bind(this));
            $('.aip-btn-save').on('click', this.saveItinerary.bind(this));
            $('.aip-btn-new').on('click', this.createNew.bind(this));
            
            // Payment
            $('.aip-btn-cancel').on('click', this.closePaymentModal.bind(this));
            
            // Warn before close
            if (aipConfig.warn_before_close === 'yes') {
                $(window).on('beforeunload', (e) => {
                    if (this.hasUnsavedChanges && this.currentItinerary) {
                        return aipConfig.texts.unsaved_changes;
                    }
                });
            }
        },
        
        toggleWidget: function() {
            $('.aip-widget-panel').toggleClass('active');
        },
        
        closeWidget: function() {
            if (this.hasUnsavedChanges && aipConfig.warn_before_close === 'yes') {
                if (!confirm(aipConfig.texts.unsaved_changes)) {
                    return;
                }
            }
            $('.aip-widget-panel').removeClass('active');
        },
        
        switchAuthTab: function(e) {
            const tab = $(e.currentTarget).data('tab');
            $('.aip-auth-tab').removeClass('active');
            $(e.currentTarget).addClass('active');
            $('.aip-auth-content').removeClass('active');
            $(`.aip-auth-content.${tab}`).addClass('active');
        },
        
        handleLogin: function(e) {
            e.preventDefault();
            // Use WordPress default login
            const form = $(e.currentTarget);
            alert('Please use WordPress login page');
        },
        
        handleRegister: function(e) {
            e.preventDefault();
            const form = $(e.currentTarget);
            const data = {
                action: 'aip_register_user',
                nonce: aipConfig.nonce,
                first_name: form.find('[name="first_name"]').val(),
                last_name: form.find('[name="last_name"]').val(),
                email: form.find('[name="email"]').val(),
                password: form.find('[name="password"]').val(),
            };
            
            $.ajax({
                url: aipConfig.ajax_url,
                type: 'POST',
                data: data,
                beforeSend: () => this.showLoading(),
                success: (response) => {
                    this.hideLoading();
                    if (response.success) {
                        this.showMainContent();
                        this.checkUserLimit();
                        alert(response.data.message);
                    } else {
                        alert(response.data.message);
                    }
                },
                error: () => {
                    this.hideLoading();
                    alert(aipConfig.texts.error);
                }
            });
        },
        
        continueAsGuest: function(e) {
            e.preventDefault();
            this.showMainContent();
            this.checkUserLimit();
        },
        
        showMainContent: function() {
            $('.aip-auth-section').hide();
            $('.aip-main-content').show();
        },
        
        toggleInterface: function(type) {
            if (type === 'chat') {
                $('.aip-chat-interface').show();
                $('.aip-form-interface').hide();
                $('.aip-toggle-chat').addClass('active');
                $('.aip-toggle-form').removeClass('active');
            } else {
                $('.aip-chat-interface').hide();
                $('.aip-form-interface').show();
                $('.aip-toggle-form').addClass('active');
                $('.aip-toggle-chat').removeClass('active');
            }
        },
        
        handleChatSend: function(e) {
            e.preventDefault();
            const input = $('.aip-chat-field');
            const message = input.val().trim();
            
            if (!message) return;
            
            // Add user message to chat
            this.addChatMessage(message, 'user');
            input.val('');
            
            // Parse message and generate itinerary
            this.parseMessageAndGenerate(message);
        },
        
        parseMessageAndGenerate: function(message) {
            // Simple parsing (you can make this more sophisticated)
            const data = {
                destination: this.extractDestination(message),
                days: this.extractDays(message) || 3,
                preferences: message,
                type: 'free'
            };
            
            this.generateItinerary(data);
        },
        
        extractDestination: function(message) {
            // Simple extraction - look for common patterns
            const words = message.toLowerCase().split(' ');
            const destinations = ['paris', 'london', 'tokyo', 'new york', 'rome', 'barcelona'];
            for (let dest of destinations) {
                if (message.toLowerCase().includes(dest)) {
                    return dest.charAt(0).toUpperCase() + dest.slice(1);
                }
            }
            return words[words.length - 1]; // Fallback to last word
        },
        
        extractDays: function(message) {
            const match = message.match(/(\d+)\s*days?/i);
            return match ? parseInt(match[1]) : null;
        },
        
        addChatMessage: function(message, type) {
            const messageHtml = `<div class="aip-message ${type}">${message}</div>`;
            $('.aip-chat-messages').append(messageHtml);
            $('.aip-chat-messages').scrollTop($('.aip-chat-messages')[0].scrollHeight);
        },
        
        handleFormSubmit: function(e) {
            e.preventDefault();
            const form = $(e.currentTarget);
            const data = {
                destination: form.find('[name="destination"]').val(),
                days: form.find('[name="days"]').val(),
                start_date: form.find('[name="start_date"]').val(),
                preferences: form.find('[name="preferences"]').val(),
                type: form.find('[name="type"]').val(),
            };
            
            this.generateItinerary(data);
        },
        
        generateItinerary: function(data) {
            // Check if premium and requires payment
            if (data.type === 'premium' && aipConfig.require_account === 'yes' && !aipConfig.is_logged_in) {
                alert(aipConfig.texts.login_required);
                return;
            }
            
            $.ajax({
                url: aipConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'aip_generate_itinerary',
                    nonce: aipConfig.nonce,
                    ...data,
                    language: aipConfig.default_language
                },
                beforeSend: () => this.showLoading(),
                success: (response) => {
                    this.hideLoading();
                    if (response.success) {
                        this.currentItinerary = response.data.itinerary_data;
                        this.currentItineraryId = response.data.itinerary_id;
                        this.hasUnsavedChanges = true;
                        
                        if (response.data.requires_payment) {
                            this.showPaymentModal();
                        } else {
                            this.displayItinerary(response.data.itinerary_data);
                            this.checkUserLimit();
                        }
                        
                        // Add bot response in chat
                        if ($('.aip-chat-interface').is(':visible')) {
                            this.addChatMessage(aipConfig.texts.generating, 'bot');
                        }
                    } else {
                        alert(response.data.message);
                        if (response.data.upgrade_required) {
                            // Show upgrade option
                            this.showUpgradeOption();
                        }
                    }
                },
                error: () => {
                    this.hideLoading();
                    alert(aipConfig.texts.error);
                }
            });
        },
        
        displayItinerary: function(data) {
            let html = `<h4>${data.destination}</h4>`;
            
            if (data.itinerary && Array.isArray(data.itinerary)) {
                data.itinerary.forEach((day) => {
                    html += `<div class="aip-day">`;
                    html += `<h5>Day ${day.day}: ${day.title || ''}</h5>`;
                    
                    if (day.activities && Array.isArray(day.activities)) {
                        day.activities.forEach((activity) => {
                            html += `<div class="aip-activity">`;
                            html += `<strong>${activity.time}</strong>: ${activity.title}<br>`;
                            html += `${activity.description || ''}<br>`;
                            if (activity.location) {
                                html += `<em>📍 ${activity.location}</em>`;
                            }
                            html += `</div>`;
                        });
                    }
                    html += `</div>`;
                });
            } else if (data.content) {
                html += `<p>${data.content}</p>`;
            }
            
            $('.aip-result-content').html(html);
            $('.aip-results').show();
            
            // Display affiliate links
            if (data.affiliate_links) {
                this.displayAffiliateLinks(data.affiliate_links, data.affiliate_style);
            }
        },
        
        displayAffiliateLinks: function(links, style) {
            let html = '';
            
            if (style === 'visible') {
                html = '<h4>Plan Your Trip:</h4><div class="aip-affiliate-buttons">';
                Object.values(links).forEach((link) => {
                    if (link) {
                        html += `<a href="${link.url}" class="aip-affiliate-button" target="_blank" rel="noopener noreferrer nofollow">`;
                        html += `<span class="icon">${link.icon}</span>`;
                        html += `<span class="label">${link.label}</span>`;
                        html += `<span class="provider">${link.provider}</span>`;
                        html += `</a>`;
                    }
                });
                html += '</div>';
            }
            
            $('.aip-affiliate-section').html(html);
        },
        
        downloadPDF: function() {
            if (!this.currentItineraryId) {
                alert('No itinerary to download');
                return;
            }
            
            $.ajax({
                url: aipConfig.ajax_url,
                type: 'POST',
                data: {
                    action: aipConfig.is_logged_in ? 'aip_generate_pdf' : 'aip_generate_pdf_guest',
                    nonce: aipConfig.nonce,
                    itinerary_id: this.currentItineraryId,
                    itinerary_data: JSON.stringify(this.currentItinerary)
                },
                beforeSend: () => this.showLoading(),
                success: (response) => {
                    this.hideLoading();
                    if (response.success) {
                        window.open(response.data.pdf_url, '_blank');
                        this.hasUnsavedChanges = false;
                    } else {
                        if (response.data.requires_payment) {
                            this.showPaymentModal();
                        } else {
                            alert(response.data.message);
                        }
                    }
                },
                error: () => {
                    this.hideLoading();
                    alert(aipConfig.texts.error);
                }
            });
        },
        
        saveItinerary: function() {
            if (!aipConfig.is_logged_in) {
                alert(aipConfig.texts.login_required);
                return;
            }
            
            if (!this.currentItineraryId) {
                alert('No itinerary to save');
                return;
            }
            
            $.ajax({
                url: aipConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'aip_save_itinerary',
                    nonce: aipConfig.nonce,
                    itinerary_id: this.currentItineraryId,
                    title: prompt('Enter a title for this itinerary:')
                },
                success: (response) => {
                    if (response.success) {
                        alert(response.data.message);
                        this.hasUnsavedChanges = false;
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        },
        
        createNew: function() {
            if (this.hasUnsavedChanges && aipConfig.warn_before_close === 'yes') {
                if (!confirm(aipConfig.texts.unsaved_changes)) {
                    return;
                }
            }
            
            this.currentItinerary = null;
            this.currentItineraryId = null;
            this.hasUnsavedChanges = false;
            $('.aip-results').hide();
            $('.aip-chat-field').val('');
            $('.aip-itinerary-form')[0].reset();
        },
        
        showPaymentModal: function() {
            $('.aip-payment-modal').show();
            this.initPayment();
        },
        
        closePaymentModal: function() {
            $('.aip-payment-modal').hide();
        },
        
        initPayment: function() {
            if (aipConfig.payment_method === 'stripe' || aipConfig.payment_method === 'both') {
                this.initStripe();
            }
            if (aipConfig.payment_method === 'paypal' || aipConfig.payment_method === 'both') {
                this.initPayPal();
            }
        },
        
        initStripe: function() {
            if (typeof Stripe === 'undefined') return;
            
            const stripe = Stripe(aipConfig.stripe_public_key);
            
            $.ajax({
                url: aipConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'aip_create_payment_intent',
                    nonce: aipConfig.nonce,
                    itinerary_id: this.currentItineraryId
                },
                success: (response) => {
                    if (response.success) {
                        const elements = stripe.elements({ clientSecret: response.data.client_secret });
                        const paymentElement = elements.create('payment');
                        paymentElement.mount('#aip-payment-element');
                        
                        $('.aip-btn-pay').off('click').on('click', async () => {
                            const { error } = await stripe.confirmPayment({
                                elements,
                                confirmParams: {
                                    return_url: window.location.href,
                                },
                                redirect: 'if_required'
                            });
                            
                            if (!error) {
                                this.verifyPayment();
                            } else {
                                alert(error.message);
                            }
                        });
                    }
                }
            });
        },
        
        initPayPal: function() {
            // PayPal integration would go here
        },
        
        verifyPayment: function() {
            $.ajax({
                url: aipConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'aip_verify_payment',
                    nonce: aipConfig.nonce,
                    itinerary_id: this.currentItineraryId
                },
                success: (response) => {
                    if (response.success) {
                        alert(aipConfig.texts.payment_success);
                        this.closePaymentModal();
                        this.displayItinerary(this.currentItinerary);
                    }
                }
            });
        },
        
        checkUserLimit: function() {
            $.ajax({
                url: aipConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'aip_check_limit',
                    nonce: aipConfig.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $('.aip-remaining-count').text(response.data.remaining);
                    }
                }
            });
        },
        
        showUpgradeOption: function() {
            if (confirm('Upgrade to premium for $' + aipConfig.premium_price + '?')) {
                $('[name="type"]').val('premium');
                $('.aip-itinerary-form').trigger('submit');
            }
        },
        
        initGoogleAuth: function() {
            if (!aipConfig.google_client_id) return;
            
            // Google Sign-In initialization
            if (typeof google !== 'undefined' && google.accounts) {
                google.accounts.id.initialize({
                    client_id: aipConfig.google_client_id,
                    callback: this.handleGoogleAuth.bind(this)
                });
                
                if ($('#aip-google-signin').length) {
                    google.accounts.id.renderButton(
                        document.getElementById('aip-google-signin'),
                        { theme: 'outline', size: 'large' }
                    );
                }
                
                if ($('#aip-google-signin-register').length) {
                    google.accounts.id.renderButton(
                        document.getElementById('aip-google-signin-register'),
                        { theme: 'outline', size: 'large' }
                    );
                }
            }
        },
        
        handleGoogleAuth: function(response) {
            $.ajax({
                url: aipConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'aip_google_auth',
                    nonce: aipConfig.nonce,
                    google_token: response.credential
                },
                success: (data) => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.data.message);
                    }
                }
            });
        },
        
        showLoading: function() {
            $('.aip-loading').show();
        },
        
        hideLoading: function() {
            $('.aip-loading').hide();
        }
    };
    
    // Initialize when document is ready
    $(document).ready(() => {
        AIPWidget.init();
    });
    
})(jQuery);

