/**
 * AI Itinerary Plugin - Frontend JavaScript
 */

(function($) {
    'use strict';
    
    const AIPWidget = {
        currentItinerary: null,
        currentItineraryId: null,
        hasUnsavedChanges: false,
        conversationState: null,
        collectedData: null,
        
        init: function() {
            this.bindEvents();
            this.initGoogleAuth();
            this.checkUserLimit();
            this.initConversation();
            this.createNotificationContainer();
        },
        
        /**
         * Create notification container for custom modals
         */
        createNotificationContainer: function() {
            if ($('#aip-notification').length === 0) {
                $('body').append(`
                    <div id="aip-notification" class="aip-notification" style="display: none;">
                        <div class="aip-notification-overlay"></div>
                        <div class="aip-notification-content">
                            <div class="aip-notification-message"></div>
                            <div class="aip-notification-buttons">
                                <button class="aip-notification-btn aip-notification-ok">OK</button>
                                <button class="aip-notification-btn aip-notification-cancel" style="display: none;">Cancel</button>
                            </div>
                        </div>
                    </div>
                `);
                
                // Bind events
                $('.aip-notification-ok').on('click', () => {
                    $('#aip-notification').hide();
                    if (this.notificationCallback) {
                        this.notificationCallback(true);
                        this.notificationCallback = null;
                    }
                });
                
                $('.aip-notification-cancel').on('click', () => {
                    $('#aip-notification').hide();
                    if (this.notificationCallback) {
                        this.notificationCallback(false);
                        this.notificationCallback = null;
                    }
                });
                
                $('.aip-notification-overlay').on('click', () => {
                    $('#aip-notification').hide();
                    if (this.notificationCallback) {
                        this.notificationCallback(false);
                        this.notificationCallback = null;
                    }
                });
            }
        },
        
        /**
         * Show custom notification (replaces alert())
         */
        showNotification: function(message, callback) {
            console.log('[AIP] Notification:', message);
            $('.aip-notification-message').html(message);
            $('.aip-notification-cancel').hide();
            $('.aip-notification-ok').text('OK');
            $('#aip-notification').show();
            this.notificationCallback = callback;
        },
        
        /**
         * Show custom confirm (replaces confirm())
         */
        showConfirm: function(message, callback) {
            console.log('[AIP] Confirm:', message);
            $('.aip-notification-message').html(message);
            $('.aip-notification-cancel').show();
            $('.aip-notification-ok').text('Yes');
            $('#aip-notification').show();
            this.notificationCallback = callback;
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
            $('.aip-payment-close').on('click', this.closePaymentModal.bind(this));
            $('.aip-payment-overlay').on('click', this.closePaymentModal.bind(this));
            
            // Payment method tabs
            $(document).on('click', '.aip-payment-tab', this.switchPaymentMethod.bind(this));
            
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
                this.showConfirm(aipConfig.texts.unsaved_changes, (confirmed) => {
                    if (confirmed) {
                        $('.aip-widget-panel').removeClass('active');
                    }
                });
            } else {
                $('.aip-widget-panel').removeClass('active');
            }
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
            this.showNotification('Please use WordPress login page or continue as guest.');
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
            
            // Send message to backend for multi-step processing
            this.processChatMessage(message);
        },
        
        initConversation: function() {
            // Initialize or load existing conversation state
            $.ajax({
                url: aipConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'aip_chat_message',
                    nonce: aipConfig.nonce,
                    message: '__init__' // Special init message
                },
                success: (response) => {
                    if (response.success && response.data.state) {
                        this.conversationState = response.data.state;
                    }
                }
            });
        },
        
        processChatMessage: function(message) {
            $.ajax({
                url: aipConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'aip_chat_message',
                    nonce: aipConfig.nonce,
                    message: message
                },
                beforeSend: () => this.showLoading(),
                success: (response) => {
                    this.hideLoading();
                    if (response.success) {
                        const data = response.data;
                        
                        // Update conversation state
                        this.conversationState = data.state;
                        
                        // Add bot response to chat
                        this.addChatMessage(data.bot_message, 'bot');
                        
                        // Check if ready to generate
                        if (data.ready_to_generate) {
                            this.collectedData = data.collected_data;
                            this.showGenerateOptions();
                        }
                    } else {
                        this.addChatMessage(response.data.message || aipConfig.texts.error, 'bot');
                    }
                },
                error: () => {
                    this.hideLoading();
                    this.addChatMessage(aipConfig.texts.error, 'bot');
                }
            });
        },
        
        showGenerateOptions: function() {
            // Add buttons to choose free or premium
            const buttonsHtml = `
                <div class="aip-generate-options">
                    <button class="aip-btn-generate-free aip-btn-primary">Free Itinerary</button>
                    <button class="aip-btn-generate-premium aip-btn-secondary">Premium Itinerary ($${aipConfig.premium_price})</button>
                </div>
            `;
            $('.aip-chat-messages').append(buttonsHtml);
            
            // Bind click events
            $('.aip-btn-generate-free').off('click').on('click', () => {
                this.generateFromConversation('free');
            });
            
            $('.aip-btn-generate-premium').off('click').on('click', () => {
                this.generateFromConversation('premium');
            });
            
            // Scroll to bottom
            $('.aip-chat-messages').scrollTop($('.aip-chat-messages')[0].scrollHeight);
        },
        
        generateFromConversation: function(type) {
            // Build destination from collected data
            // New AI system stores destination as a single field
            const destination = this.collectedData.destination || 
                               `${this.collectedData.region || ''}, ${this.collectedData.country || ''}`.trim().replace(/^,\s*/, '');
            
            // Build preferences object
            const preferences = {
                budget: this.collectedData.budget,
                interests: this.collectedData.interests,
                pace: this.collectedData.pace,
                travel_style: this.collectedData.travel_style
            };
            
            const data = {
                destination: destination,
                days: this.collectedData.days || 3,
                preferences: JSON.stringify(preferences),
                type: type
            };
            
            // Remove generate options
            $('.aip-generate-options').remove();
            
            // Generate itinerary
            this.generateItinerary(data);
        },
        
        parseMessageAndGenerate: function(message) {
            // This function is no longer used for chat, but kept for backward compatibility
            // The new system uses processChatMessage instead
            this.processChatMessage(message);
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
            console.log('[AIP] Generating itinerary with data:', data);
            
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
                    console.log('[AIP] Generate itinerary response:', response);
                    this.hideLoading();
                    if (response.success) {
                        this.currentItinerary = response.data.itinerary_data;
                        this.currentItineraryId = response.data.itinerary_id;
                        this.hasUnsavedChanges = true;
                        
                        console.log('[AIP] Requires payment:', response.data.requires_payment);
                        console.log('[AIP] Current itinerary ID:', this.currentItineraryId);
                        
                        if (response.data.requires_payment) {
                            console.log('[AIP] Showing payment modal...');
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
                        console.error('[AIP] Error:', response.data.message);
                        alert(response.data.message);
                        if (response.data.upgrade_required) {
                            // Show upgrade option
                            this.showUpgradeOption();
                        }
                    }
                },
                error: () => {
                    console.error('[AIP] AJAX error generating itinerary');
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
            this.collectedData = null;
            $('.aip-results').hide();
            $('.aip-chat-field').val('');
            $('.aip-itinerary-form')[0].reset();
            
            // Reset conversation
            this.resetConversation();
        },
        
        resetConversation: function() {
            $.ajax({
                url: aipConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'aip_reset_conversation',
                    nonce: aipConfig.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.conversationState = response.data.state;
                        
                        // Clear chat messages and add initial bot message
                        $('.aip-chat-messages').html('');
                        this.addChatMessage(response.data.bot_message, 'bot');
                    }
                }
            });
        },
        
        showPaymentModal: function() {
            console.log('[AIP] showPaymentModal called');
            console.log('[AIP] Payment method:', aipConfig.payment_method);
            console.log('[AIP] Modal element exists:', $('.aip-payment-modal').length > 0);
            
            $('.aip-payment-modal').show();
            console.log('[AIP] Modal display set to show');
            
            // Initialize payment based on configured method
            if (aipConfig.payment_method === 'stripe') {
                console.log('[AIP] Initializing Stripe only');
                this.initStripe();
            } else if (aipConfig.payment_method === 'paypal') {
                console.log('[AIP] Initializing PayPal only');
                this.initPayPal();
            } else if (aipConfig.payment_method === 'both') {
                console.log('[AIP] Initializing Stripe (Both mode)');
                // Initialize the active tab (Stripe by default)
                this.initStripe();
            } else {
                console.error('[AIP] No payment method configured!');
                this.showNotification('Payment system is not configured. Please contact the administrator.');
            }
        },
        
        closePaymentModal: function() {
            $('.aip-payment-modal').hide();
            
            // Clean up
            $('#aip-payment-element').html('');
            $('#aip-paypal-button-container').html('');
            $('.aip-payment-loading').remove();
            $('.aip-btn-pay').prop('disabled', false).text('Pay Now');
        },
        
        switchPaymentMethod: function(e) {
            const method = $(e.currentTarget).data('method');
            
            // Update active tab
            $('.aip-payment-tab').removeClass('active');
            $(e.currentTarget).addClass('active');
            
            // Show/hide containers
            if (method === 'stripe') {
                $('#aip-stripe-container').show();
                $('#aip-paypal-container').hide();
                
                // Initialize Stripe if not already done
                if (!this.stripe) {
                    this.initStripe();
                }
            } else if (method === 'paypal') {
                $('#aip-stripe-container').hide();
                $('#aip-paypal-container').show();
                
                // Initialize PayPal if not already done
                if ($('#aip-paypal-button-container').is(':empty')) {
                    this.initPayPal();
                }
            }
        },
        
        initStripe: function() {
            if (typeof Stripe === 'undefined') {
                console.error('Stripe.js not loaded');
                return;
            }
            
            if (!aipConfig.stripe_public_key) {
                this.showNotification('Stripe is not configured. Please contact the site administrator.');
                return;
            }
            
            // Show loading
            $('.aip-payment-content').prepend('<div class="aip-payment-loading">Setting up payment...</div>');
            
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
                    $('.aip-payment-loading').remove();
                    
                    if (response.success) {
                        const elements = stripe.elements({ 
                            clientSecret: response.data.client_secret,
                            appearance: {
                                theme: 'stripe',
                                variables: {
                                    colorPrimary: aipConfig.primary_color || '#2271b1',
                                }
                            }
                        });
                        
                        const paymentElement = elements.create('payment', {
                            layout: 'tabs'
                        });
                        paymentElement.mount('#aip-payment-element');
                        
                        // Store for later use
                        this.stripeElements = elements;
                        this.stripe = stripe;
                        this.currentPaymentIntentId = response.data.payment_intent_id;
                        
                        $('.aip-btn-pay').off('click').on('click', async () => {
                            await this.processStripePayment();
                        });
                    } else {
                        this.showNotification(response.data.message || 'Failed to initialize payment');
                        this.closePaymentModal();
                    }
                },
                error: () => {
                    $('.aip-payment-loading').remove();
                    this.showNotification('Failed to initialize payment. Please try again.');
                    this.closePaymentModal();
                }
            });
        },
        
        processStripePayment: async function() {
            if (!this.stripe || !this.stripeElements) {
                this.showNotification('Payment system not initialized');
                return;
            }
            
            // Disable pay button
            $('.aip-btn-pay').prop('disabled', true).text('Processing...');
            
            const { error } = await this.stripe.confirmPayment({
                elements: this.stripeElements,
                confirmParams: {
                    return_url: window.location.href,
                },
                redirect: 'if_required'
            });
            
            if (error) {
                // Show error message
                this.showNotification(error.message);
                $('.aip-btn-pay').prop('disabled', false).text('Pay Now');
            } else {
                // Payment succeeded
                this.verifyStripePayment();
            }
        },
        
        verifyStripePayment: function() {
            $.ajax({
                url: aipConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'aip_verify_payment',
                    nonce: aipConfig.nonce,
                    payment_intent_id: this.currentPaymentIntentId,
                    itinerary_id: this.currentItineraryId
                },
                success: (response) => {
                    if (response.success) {
                        alert(aipConfig.texts.payment_success);
                        this.closePaymentModal();
                        this.displayItinerary(this.currentItinerary);
                        this.hasUnsavedChanges = false;
                        
                        // Update limits
                        this.checkUserLimit();
                    } else {
                        alert(response.data.message || 'Payment verification failed');
                        $('.aip-btn-pay').prop('disabled', false).text('Pay Now');
                    }
                },
                error: () => {
                    alert('Payment verification failed. Please contact support.');
                    $('.aip-btn-pay').prop('disabled', false).text('Pay Now');
                }
            });
        },
        
        initPayPal: function() {
            if (typeof paypal === 'undefined') {
                console.error('PayPal SDK not loaded');
                return;
            }
            
            // Clear any existing PayPal buttons
            $('#aip-paypal-button-container').html('');
            
            paypal.Buttons({
                style: {
                    layout: 'vertical',
                    color: 'blue',
                    shape: 'rect',
                    label: 'pay'
                },
                
                createOrder: (data, actions) => {
                    // Show loading
                    $('.aip-payment-content').prepend('<div class="aip-payment-loading">Creating order...</div>');
                    
                    return $.ajax({
                        url: aipConfig.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'aip_create_paypal_order',
                            nonce: aipConfig.nonce,
                            itinerary_id: this.currentItineraryId
                        }
                    }).then((response) => {
                        $('.aip-payment-loading').remove();
                        
                        if (response.success) {
                            return response.data.order_id;
                        } else {
                            throw new Error(response.data.message || 'Failed to create PayPal order');
                        }
                    }).catch((error) => {
                        $('.aip-payment-loading').remove();
                        alert(error.message || 'Failed to create PayPal order');
                        throw error;
                    });
                },
                
                onApprove: (data, actions) => {
                    // Show loading
                    $('.aip-payment-content').prepend('<div class="aip-payment-loading">Processing payment...</div>');
                    
                    return actions.order.capture().then(() => {
                        return this.verifyPayPalPayment(data.orderID);
                    });
                },
                
                onError: (err) => {
                    console.error('PayPal error:', err);
                    alert('PayPal payment failed. Please try again.');
                    $('.aip-payment-loading').remove();
                },
                
                onCancel: () => {
                    alert('Payment cancelled');
                    $('.aip-payment-loading').remove();
                }
            }).render('#aip-paypal-button-container');
        },
        
        verifyPayPalPayment: function(orderId) {
            return $.ajax({
                url: aipConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'aip_verify_paypal_payment',
                    nonce: aipConfig.nonce,
                    order_id: orderId,
                    itinerary_id: this.currentItineraryId
                },
                success: (response) => {
                    $('.aip-payment-loading').remove();
                    
                    if (response.success) {
                        alert(aipConfig.texts.payment_success);
                        this.closePaymentModal();
                        this.displayItinerary(this.currentItinerary);
                        this.hasUnsavedChanges = false;
                        
                        // Update limits
                        this.checkUserLimit();
                    } else {
                        alert(response.data.message || 'Payment verification failed');
                    }
                },
                error: () => {
                    $('.aip-payment-loading').remove();
                    alert('Payment verification failed. Please contact support.');
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
    
    // Expose to global scope for debugging and external access
    window.AIPWidget = AIPWidget;
    
})(jQuery);

