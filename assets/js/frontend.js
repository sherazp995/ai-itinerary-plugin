(function($){
    var state = {
        isOpen: false,
        currentItinerary: null,
        promptsRemaining: null,
        messages: [],
        isLoading: false
    };

    $(document).ready(function(){
        var opts = window.aiItinerary && window.aiItinerary.options ? window.aiItinerary.options : {};

        // Check prompt count on load
        checkPromptCount();

        // Toggle widget
        $('body').on('click', '.ai-open-widget', function(e){
            e.preventDefault();
            var $w = $(this).closest('.ai-itinerary-widget');
            state.isOpen = !state.isOpen;
            $w.toggleClass('ai-widget-open');
            $w.find('.ai-widget-panel').attr('aria-hidden', !state.isOpen);
        });

        // Handle form submission (for form interface)
        $('body').on('submit', '.ai-form', function(e){
            e.preventDefault();
            var $form = $(this);
            var destination = $form.find('[name="destination"]').val();
            var startDate = $form.find('[name="start_date"]').val();
            var endDate = $form.find('[name="end_date"]').val();

            if (!destination) {
                alert('Please enter a destination');
                return;
            }

            // Calculate days
            var start = new Date(startDate);
            var end = new Date(endDate);
            var days = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) || 1;

            generateItinerary(destination, days, $form.closest('.ai-itinerary-widget'));
        });

        // Handle chat Send button (NEW - for chat interface)
        $('body').on('click', '.ai-send-btn', function(e){
            e.preventDefault();
            var $btn = $(this);
            var $textarea = $btn.closest('.ai-input-wrapper').find('.ai-input');
            var destination = $textarea.val().trim();
            
            if (!destination) {
                alert('Please enter a destination');
                return;
            }
            
            if (state.isLoading) {
                return; // Prevent double-click
            }

            generateItinerary(destination, 1, $textarea.closest('.ai-itinerary-widget'));
            $textarea.val('');
        });

        // Handle chat input - Enter key (support for Enter only, Shift+Enter for newline)
        $('body').on('keypress', '.ai-input', function(e){
            if (e.which === 13 && !e.shiftKey) { // Enter without Shift
                e.preventDefault();
                $(this).closest('.ai-input-wrapper').find('.ai-send-btn').click();
            }
            // Shift+Enter allows newline
        });

        // Save itinerary
        $('body').on('click', '.ai-save-itinerary', function(e){
            e.preventDefault();
            if (!state.currentItinerary) {
                alert('No itinerary to save. Generate one first!');
                return;
            }
            saveItinerary();
        });

        // Download PDF
        $('body').on('click', '.ai-download-pdf', function(e){
            e.preventDefault();
            if (!state.currentItinerary) {
                alert('No itinerary to download. Generate one first!');
                return;
            }
            downloadPdf();
        });

        // Warn on close if configured
        if (opts.warn_on_close === 'yes' || opts.warn_on_close === true) {
            window.addEventListener('beforeunload', function(e){
                if (state.isOpen && state.currentItinerary) {
                    var msg = 'You have an unsaved itinerary. Are you sure you want to leave?';
                    (e || window.event).returnValue = msg;
                    return msg;
                }
            });
        }

        function generateItinerary(destination, days, $widget) {
            if (state.isLoading) return;
            
            state.isLoading = true;
            var $body = $widget.find('.ai-widget-body');
            $body.html('<p class="ai-loading">⏳ Generating your itinerary...</p>');

            $.ajax({
                type: 'POST',
                url: aiItinerary.ajax_url,
                data: {
                    action: 'ai_generate_itinerary',
                    nonce: aiItinerary.nonce,
                    destination: destination,
                    days: days,
                    language: opts.output_language || 'en',
                    style: opts.pdf_style || 'minimal'
                },
                success: function(response){
                    state.isLoading = false;
                    if (response.success) {
                        state.currentItinerary = {
                            content: response.data.itinerary,
                            destination: response.data.destination,
                            days: response.data.days,
                            language: response.data.language
                        };
                        renderItinerary($body, response.data.itinerary, $widget);
                        // Enable save and download buttons
                        $widget.find('.ai-save-itinerary').prop('disabled', false);
                        $widget.find('.ai-download-pdf').prop('disabled', false);
                    } else {
                        $body.html('<p class="ai-error">❌ Error: ' + escapeHtml(response.data.message || 'Unknown error') + '</p>');
                        // Disable buttons on error
                        $widget.find('.ai-save-itinerary').prop('disabled', true);
                        $widget.find('.ai-download-pdf').prop('disabled', true);
                    }
                },
                error: function(xhr, status, error){
                    state.isLoading = false;
                    var errorMsg = 'Failed to connect to server';
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMsg = xhr.responseJSON.data.message;
                    }
                    $body.html('<p class="ai-error">❌ ' + escapeHtml(errorMsg) + '</p>');
                    $widget.find('.ai-save-itinerary').prop('disabled', true);
                    $widget.find('.ai-download-pdf').prop('disabled', true);
                }
            });
        }

        function renderItinerary($container, content, $widget) {
            $container.html('');
            var $display = $('<div class="ai-itinerary-display"></div>');
            $display.html('<div class="ai-itinerary-content">' + escapeHtml(content) + '</div>');
            $container.append($display);
        }

        function saveItinerary() {
            if (!state.currentItinerary) return;

            $.ajax({
                type: 'POST',
                url: aiItinerary.ajax_url,
                data: {
                    action: 'ai_save_itinerary',
                    nonce: aiItinerary.nonce,
                    title: state.currentItinerary.destination + ' - ' + state.currentItinerary.days + ' days',
                    data: JSON.stringify(state.currentItinerary)
                },
                success: function(response){
                    if (response.success) {
                        alert('✅ Itinerary saved successfully!');
                        state.currentItinerary = null;
                        // Disable save/download buttons after saving
                        var $widget = $('.ai-itinerary-widget.ai-widget-open');
                        $widget.find('.ai-save-itinerary').prop('disabled', true);
                        $widget.find('.ai-download-pdf').prop('disabled', true);
                    } else {
                        alert('❌ Error: ' + (response.data.message || 'Failed to save'));
                    }
                },
                error: function(){
                    alert('❌ Failed to save itinerary');
                }
            });
        }

        function downloadPdf() {
            // Stub for now - will implement PDF generation next
            alert('📄 PDF download will be available soon!');
        }

        function checkPromptCount() {
            $.ajax({
                type: 'POST',
                url: aiItinerary.ajax_url,
                data: {
                    action: 'ai_check_prompt_count',
                    nonce: aiItinerary.nonce
                },
                success: function(response){
                    if (response.success) {
                        state.promptsRemaining = response.data.remaining;
                        updatePromptDisplay(response.data);
                    }
                }
            });
        }

        function updatePromptDisplay(data) {
            var $widget = $('.ai-itinerary-widget');
            var msg = '';
            
            if (data.remaining === 0) {
                msg = '<p class="ai-info">⚠️ You have reached your free prompt limit. <a href="/pricing">Upgrade to Premium</a> for unlimited access.</p>';
            } else if (data.remaining <= 2) {
                msg = '<p class="ai-info">ℹ️ You have ' + data.remaining + ' prompt(s) remaining today.</p>';
            }
            
            if (msg) {
                var $existing = $widget.find('.ai-prompt-info');
                if ($existing.length) {
                    $existing.replaceWith(msg);
                } else {
                    $widget.find('.ai-widget-body').prepend($('<div class="ai-prompt-info">' + msg + '</div>'));
                }
            }
        }

        function escapeHtml(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    });
})(jQuery);
