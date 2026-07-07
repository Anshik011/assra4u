jQuery(document).ready(function($) {
    let fileQueue = [];
    let isProcessing = false;
    let currentIndex = 0;
    let totalFiles = 0;
    let processedCount = 0;
    let completedCount = 0;
    let failedCount = 0;
    let skippedCount = 0;

    const $dropzone = $('#assra-dropzone');
    const $fileInput = $('#assra-file-input');
    const $startBtn = $('#assra-btn-start');
    const $pauseBtn = $('#assra-btn-pause');
    const $cancelBtn = $('#assra-btn-cancel');
    const $progressBar = $('#assra-progress-fill');
    const $progressText = $('#assra-progress-text');
    const $statProcessed = $('#assra-stat-processed');
    const $statCompleted = $('#assra-stat-completed');
    const $statFailed = $('#assra-stat-failed');
    const $statSkipped = $('#assra-stat-skipped');
    const $queueBody = $('#assra-queue-body');
    const $noFiles = $('#assra-no-files');
    const $progressWrapper = $('#assra-progress-wrapper');
    const $retryBtn = $('#assra-btn-retry');

    $retryBtn.hide();

    // 1. Drag & Drop Event Handlers
    $dropzone.on('click', function() {
        if (!isProcessing) {
            $fileInput.click();
        }
    });

    $dropzone.on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if (!isProcessing) {
            $(this).addClass('dragover');
        }
    });

    $dropzone.on('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
    });

    $dropzone.on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
        if (isProcessing) return;

        const files = e.originalEvent.dataTransfer.files;
        handleFilesSelected(files);
    });

    $fileInput.on('change', function() {
        handleFilesSelected(this.files);
        this.value = ''; // Reset input to allow selecting same files again
    });

    // 2. Handle Selected Files
    function handleFilesSelected(files) {
        if (!files.length) return;

        $noFiles.hide();
        $progressWrapper.show();

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            
            // Only allow images
            if (!file.type.match('image.*')) {
                alert(`File "${file.name}" is not an image and will be skipped.`);
                continue;
            }

            const queueIndex = fileQueue.length;
            const fileObj = {
                file: file,
                status: 'pending',
                error: '',
                post_id: null
            };
            fileQueue.push(fileObj);

            // Append row to queue table
            const reader = new FileReader();
            const rowId = `assra-queue-row-${queueIndex}`;
            
            const $row = $(`
                <tr id="${rowId}">
                    <td><img class="assra-thumb-preview" src="" alt="Preview"></td>
                    <td>
                        <strong class="assra-file-name"></strong>
                        <div class="assra-row-details">Size: ${(file.size / 1024).toFixed(1)} KB</div>
                    </td>
                    <td><span class="assra-status-badge assra-status-pending">Pending</span></td>
                    <td class="assra-row-result">Waiting to start...</td>
                </tr>
            `);

            $row.find('.assra-file-name').text(file.name);
            $queueBody.append($row);

            // Load thumbnail preview
            reader.onload = function(e) {
                $(`#${rowId} .assra-thumb-preview`).attr('src', e.target.result);
            };
            reader.readAsDataURL(file);
        }

        updateStats();
        checkButtons();
    }

    // 3. Control Buttons Event Handlers
    $startBtn.on('click', function() {
        const apiKey = $('#assra-api-key').val().trim();
        if (!apiKey) {
            alert('Please enter your Gemini API Key in the settings before starting.');
            $('#assra-api-key').focus();
            return;
        }

        // Save API key via ajax
        saveApiKey(apiKey);

        isProcessing = true;
        $startBtn.prop('disabled', true);
        $pauseBtn.prop('disabled', false);
        $cancelBtn.prop('disabled', false);
        $('#assra-api-key, #assra-category, #assra-year').prop('disabled', true);

        processNext();
    });

    $pauseBtn.on('click', function() {
        isProcessing = false;
        $startBtn.prop('disabled', false).find('span').text('Resume Import');
        $pauseBtn.prop('disabled', true);
        checkButtons();
    });

    $retryBtn.on('click', function() {
        if (isProcessing) return;

        isProcessing = true;
        $startBtn.prop('disabled', true);
        $retryBtn.prop('disabled', true);
        $pauseBtn.prop('disabled', false);
        $cancelBtn.prop('disabled', false);
        $('#assra-api-key, #assra-category, #assra-year').prop('disabled', true);

        // Reset all failed items in the queue to pending
        fileQueue.forEach((fileObj, index) => {
            if (fileObj.status === 'failed') {
                fileObj.status = 'pending';
                fileObj.error = '';
                updateRowStatus(index, 'pending', 'Retrying...');
                failedCount--;
                processedCount--;
            }
        });

        currentIndex = 0; // Restart scanning queue from the beginning
        updateStats();
        processNext();
    });

    $cancelBtn.on('click', function() {
        if (confirm('Are you sure you want to cancel the entire import queue? Already processed images will remain in your Media Library and Gallery.')) {
            isProcessing = false;
            fileQueue = [];
            currentIndex = 0;
            processedCount = 0;
            completedCount = 0;
            failedCount = 0;
            skippedCount = 0;
            totalFiles = 0;

            $queueBody.empty();
            $noFiles.show();
            $progressWrapper.hide();
            $progressBar.css('width', '0%');
            $progressText.text('0%');
            
            $startBtn.prop('disabled', false).find('span').text('Start Import');
            $pauseBtn.prop('disabled', true);
            $cancelBtn.prop('disabled', true);
            $('#assra-api-key, #assra-category, #assra-year').prop('disabled', false);
            updateStats();
        }
    });

    // 4. Queue Processor
    function processNext() {
        if (!isProcessing) return;

        // Find next pending file
        while (currentIndex < fileQueue.length && fileQueue[currentIndex].status !== 'pending') {
            currentIndex++;
        }

        if (currentIndex >= fileQueue.length) {
            // Import finished!
            isProcessing = false;
            alert('AI Bulk Import Completed!');
            $startBtn.prop('disabled', false).find('span').text('Start New Import');
            $pauseBtn.prop('disabled', true);
            $('#assra-api-key, #assra-category, #assra-year').prop('disabled', false);
            checkButtons();
            return;
        }

        const fileObj = fileQueue[currentIndex];
        const rowId = `assra-queue-row-${currentIndex}`;
        const $row = $(`#${rowId}`);

        // Update Row status
        updateRowStatus(currentIndex, 'uploading', 'Uploading image...');

        // Build FormData
        const formData = new FormData();
        formData.append('action', 'assra_ai_import_single');
        formData.append('security', assra_importer_nonce);
        formData.append('image', fileObj.file);
        formData.append('category', $('#assra-category').val());
        formData.append('year', $('#assra-year').val());

        // Perform AJAX Request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        const percentComplete = Math.round((evt.loaded / evt.total) * 100);
                        if (percentComplete < 100) {
                            $row.find('.assra-row-result').text(`Uploading: ${percentComplete}%`);
                        } else {
                            updateRowStatus(currentIndex, 'analyzing', 'AI is analyzing & generating SEO metadata...');
                        }
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    if (data.skipped) {
                        // Image is a duplicate
                        fileObj.status = 'skipped';
                        updateRowStatus(currentIndex, 'skipped', data.message);
                        skippedCount++;
                    } else {
                        // Successfully imported
                        fileObj.status = 'completed';
                        fileObj.post_id = data.gallery_post_id;
                        
                        const detailsHtml = `
                            <strong>Title:</strong> ${data.title}<br>
                            <strong>Category:</strong> ${data.category}<br>
                            <strong>SEO File:</strong> <span class="assra-row-details">${data.filename}</span>
                        `;
                        updateRowStatus(currentIndex, 'completed', detailsHtml);
                        completedCount++;
                    }
                } else {
                    // API/PHP processing error
                    fileObj.status = 'failed';
                    fileObj.error = response.data || 'Unknown error';
                    updateRowStatus(currentIndex, 'failed', `<span class="assra-row-error">Error: ${fileObj.error}</span>`);
                    failedCount++;
                }

                finalizeItemProgress();
            },
            error: function(xhr, status, error) {
                // Connection or server error
                fileObj.status = 'failed';
                fileObj.error = error || 'Network connection failed';
                updateRowStatus(currentIndex, 'failed', `<span class="assra-row-error">Network Error: ${fileObj.error}</span>`);
                failedCount++;

                finalizeItemProgress();
            }
        });
    }

    function finalizeItemProgress() {
        processedCount++;
        currentIndex++;
        
        updateStats();
        
        // Recurse to next item in queue
        // A 4000ms delay helps avoid hitting Gemini API free tier rate limits (15 RPM)
        setTimeout(processNext, 4000);
    }

    // 5. Helper Functions
    function updateRowStatus(index, status, resultHtml) {
        const rowId = `assra-queue-row-${index}`;
        const $row = $(`#${rowId}`);
        const $badge = $row.find('.assra-status-badge');
        
        fileQueue[index].status = status;

        // Update badge classes
        $badge.removeClass(function(index, className) {
            return (className.match(/(^|\s)assra-status-\S+/g) || []).join(' ');
        }).addClass(`assra-status-${status}`).text(capitalize(status));

        // Update result text
        $row.find('.assra-row-result').html(resultHtml);
    }

    function updateStats() {
        totalFiles = fileQueue.length;
        
        // Count totals
        let pending = 0;
        fileQueue.forEach(item => {
            if (item.status === 'pending') pending++;
        });

        $statProcessed.text(`${processedCount} / ${totalFiles}`);
        $statCompleted.text(completedCount);
        $statFailed.text(failedCount);
        $statSkipped.text(skippedCount);

        // Update progress bar
        if (totalFiles > 0) {
            const percent = Math.round((processedCount / totalFiles) * 100);
            $progressBar.css('width', `${percent}%`);
            $progressText.text(`${percent}%`);
        } else {
            $progressBar.css('width', '0%');
            $progressText.text('0%');
        }
    }

    function checkButtons() {
        if (fileQueue.length > 0) {
            $startBtn.prop('disabled', isProcessing);
            $cancelBtn.prop('disabled', false);
            
            if (failedCount > 0 && !isProcessing) {
                $retryBtn.show().prop('disabled', false);
            } else {
                $retryBtn.prop('disabled', true);
                if (isProcessing) {
                    $retryBtn.hide();
                }
            }
        } else {
            $startBtn.prop('disabled', true);
            $pauseBtn.prop('disabled', true);
            $cancelBtn.prop('disabled', true);
            $retryBtn.prop('disabled', true).hide();
        }
    }

    function saveApiKey(apiKey) {
        $.post(ajaxurl, {
            action: 'assra_save_gemini_key',
            security: assra_importer_nonce,
            api_key: apiKey
        });
    }

    function capitalize(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }

    // Capitalize first loaded stats
    updateStats();
    checkButtons();
});
