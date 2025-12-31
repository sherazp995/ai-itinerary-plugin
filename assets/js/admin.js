/**
 * AI Itinerary Plugin - Admin JavaScript
 */

(function($) {
    'use strict';
    
    const AIPAdmin = {
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            // Confirm delete for affiliate providers
            $('.aip-delete-provider').on('click', function(e) {
                if (!confirm('Are you sure you want to delete this affiliate provider?')) {
                    e.preventDefault();
                    return false;
                }
            });
            
            // Auto-generate slug from name for affiliate providers
            $('#provider_name').on('blur', function() {
                const name = $(this).val();
                const slugField = $('#provider_slug');
                
                // Only auto-fill if slug is empty
                if (name && !slugField.val()) {
                    const slug = name.toLowerCase()
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/^-+|-+$/g, '');
                    slugField.val(slug);
                }
            });
            
            // Template placeholder helper
            this.setupTemplateHelper();
        },
        
        setupTemplateHelper: function() {
            // Add helper text with clickable placeholders for link template
            const templateField = $('#link_template');
            if (templateField.length) {
                const placeholders = [
                    '{affiliate_id}',
                    '{destination}',
                    '{destination_slug}',
                    '{check_in}',
                    '{check_out}',
                    '{destination_iata}',
                    '{origin}'
                ];
                
                // Create helper buttons
                const helperDiv = $('<div class="aip-template-helper"></div>');
                helperDiv.append('<p><strong>Click to insert:</strong></p>');
                
                placeholders.forEach(function(placeholder) {
                    const btn = $('<button type="button" class="button button-small">' + placeholder + '</button>');
                    btn.on('click', function() {
                        const textarea = templateField[0];
                        const start = textarea.selectionStart;
                        const end = textarea.selectionEnd;
                        const text = templateField.val();
                        
                        // Insert placeholder at cursor position
                        const newText = text.substring(0, start) + placeholder + text.substring(end);
                        templateField.val(newText);
                        
                        // Set cursor position after inserted text
                        textarea.selectionStart = textarea.selectionEnd = start + placeholder.length;
                        textarea.focus();
                    });
                    helperDiv.append(btn);
                    helperDiv.append(' ');
                });
                
                templateField.after(helperDiv);
            }
        }
    };
    
    $(document).ready(function() {
        AIPAdmin.init();
        console.log('AI Itinerary Admin loaded');
    });
    
})(jQuery);

