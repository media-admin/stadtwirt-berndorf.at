(function($) {
    'use strict';
    
    // Mark that external script is loaded
    window.cf7AcfTagGeneratorLoaded = true;
    
    function initACFTagGenerator() {
        var $form = $('#tag-generator-panel-acf_field');
        if ($form.length === 0) return;
        
        var $nameInput = $form.find('input[name="name"]');
        var $acfFieldSelect = $form.find('select[name="acf-field"]');
        var $fieldKeyDisplay = $form.find('#tag-generator-panel-acf_field-field-key-display');
        var $warningMessage = $form.find('.acf-field-warning');
        // Try multiple selectors to find the tag input
        var $tagInput = $form.find('#tag-generator-panel-acf_field-tag-input');
        if ($tagInput.length === 0) {
            $tagInput = $form.closest('.tag-generator-panel').find('input.tag.code');
        }
        if ($tagInput.length === 0) {
            $tagInput = $form.closest('.tag-generator-panel').find('input[name="acf_field"]');
        }
        
        function updateTag() {
            var name = $nameInput.val();
            var acfField = $acfFieldSelect.val();
            
            // Update field key display
            if (acfField) {
                $fieldKeyDisplay.val(acfField);
                // Hide warning message when field is selected
                $warningMessage.hide();
            } else {
                $fieldKeyDisplay.val('');
                // Show warning message when no field is selected
                $warningMessage.show();
            }
            
            // Validate that both name and ACF field are selected
            if (name && acfField) {
                // Wrap the option in quotes for proper CF7 parsing
                // Don't add asterisk - required validation will be handled on frontend based on ACF field settings
                var tag = '[acf_field ' + name + ' "acf-field:' + acfField + '"]';
                
                $tagInput.val(tag);
                
                // Enable insert button
                $form.closest('.tag-generator-panel').find('.insert-tag').prop('disabled', false).removeClass('button-disabled');
            } else {
                // Clear tag if fields are missing
                $tagInput.val('');
                
                // Disable insert button if ACF field is not selected
                if (!acfField) {
                    $form.closest('.tag-generator-panel').find('.insert-tag').prop('disabled', true).addClass('button-disabled');
                } else {
                    $form.closest('.tag-generator-panel').find('.insert-tag').prop('disabled', false).removeClass('button-disabled');
                }
            }
        }
        
        // Intercept the insert button click to ensure full tag is inserted
        $form.closest('.tag-generator-panel').on('click', '.insert-tag', function(e) {
            var acfField = $acfFieldSelect.val();
            var name = $nameInput.val();
            
            if (!acfField) {
                e.preventDefault();
                e.stopPropagation();
                alert('Please select an ACF field from the dropdown before inserting the tag.');
                $acfFieldSelect.focus();
                return false;
            }
            
            if (!name) {
                e.preventDefault();
                e.stopPropagation();
                alert('Please enter a field name before inserting the tag.');
                $nameInput.focus();
                return false;
            }
            
            // Wrap the option in quotes for proper CF7 parsing
            // Don't add asterisk - required validation will be handled on frontend based on ACF field settings
            var fullTag = '[acf_field ' + name + ' "acf-field:' + acfField + '"]';
            
            // Set the value multiple ways to ensure CF7 picks it up
            $tagInput.val(fullTag);
            $tagInput.attr('value', fullTag);
            
            // Force update events
            $tagInput.trigger('change').trigger('input');
            
            // Use a longer delay and verify multiple times
            var attempts = 0;
            var verifyTag = setInterval(function() {
                attempts++;
                var currentValue = $tagInput.val();
                
                if (currentValue !== fullTag) {
                    $tagInput.val(fullTag);
                    $tagInput.attr('value', fullTag);
                }
                // Stop after 5 attempts (50ms)
                if (attempts >= 5) {
                    clearInterval(verifyTag);
                }
            }, 10);
            
            // Final verification right before CF7 processes
            setTimeout(function() {
                var finalValue = $tagInput.val();
                if (finalValue !== fullTag) {
                    $tagInput.val(fullTag);
                    $tagInput.attr('value', fullTag);
                }
            }, 50);
        });
        
        $nameInput.on('keyup change', updateTag);
        $acfFieldSelect.on('change', updateTag);
        
        // Initial update
        updateTag();
        
        // Set initial warning visibility
        if ($acfFieldSelect.val()) {
            $warningMessage.hide();
        } else {
            $warningMessage.show();
        }
    }
    
    // Initialize when document is ready
    $(document).ready(function() {
        initACFTagGenerator();
    });
    
    // Also initialize when tag generator panel is opened (CF7 uses dynamic loading)
    $(document).on('click', '.tag-generator-list [data-tag="acf_field"]', function() {
        setTimeout(initACFTagGenerator, 100);
    });
    
    // Hook into CF7's tag insertion to ensure full tag is used
    $(document).on('wpcf7tgpanel:active', function(e, panel) {
        if (panel && panel.attr('id') === 'tag-generator-panel-acf_field') {
            setTimeout(initACFTagGenerator, 50);
        }
    });
    
    // Hook into mousedown to set value before click is processed
    $(document).on('mousedown', '.tag-generator-panel .insert-tag', function(e) {
        var $panel = $(this).closest('.tag-generator-panel');
        var $form = $panel.find('#tag-generator-panel-acf_field');
        
        if ($form.length > 0) {
            var $nameInput = $form.find('input[name="name"]');
            var $acfFieldSelect = $form.find('select[name="acf-field"]');
            var $tagInput = $form.find('#tag-generator-panel-acf_field-tag-input');
            
            if ($tagInput.length === 0) {
                $tagInput = $panel.find('input.tag.code');
            }
            if ($tagInput.length === 0) {
                $tagInput = $panel.find('input[name="acf_field"]');
            }
            
            var name = $nameInput.val();
            var acfField = $acfFieldSelect.val();
            
            if (name && acfField) {
                // Wrap the option in quotes for proper CF7 parsing
                // Don't add asterisk - required validation will be handled on frontend based on ACF field settings
                var fullTag = '[acf_field ' + name + ' "acf-field:' + acfField + '"]';
                
                // Set value immediately on mousedown (before click)
                $tagInput.val(fullTag);
                $tagInput.attr('value', fullTag);
                
                // Set on all possible inputs CF7 might read from
                $panel.find('input[name="acf_field"]').val(fullTag).attr('value', fullTag);
                $panel.find('input.tag.code').val(fullTag).attr('value', fullTag);
                
                // Force DOM update
                if ($tagInput[0]) {
                    $tagInput[0].setAttribute('value', fullTag);
                }
                if ($panel.find('input[name="acf_field"]').length > 0) {
                    $panel.find('input[name="acf_field"]')[0].setAttribute('value', fullTag);
                }
            }
        }
    });
    
})(jQuery);

