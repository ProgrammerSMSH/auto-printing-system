<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remote PDF Printing - Upload</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/dropzone.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .container {
            max-width: 800px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .preview-container {
            border: 2px dashed #dee2e6;
            border-radius: 5px;
            padding: 20px;
            text-align: center;
            min-height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
        .preview-container.dragover {
            border-color: #0d6efd;
            background-color: rgba(13, 110, 253, 0.05);
        }
        .qr-code {
            max-width: 200px;
            margin: 20px auto;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .status-pending { background-color: #ffc107; color: #000; }
        .status-processing { background-color: #0dcaf0; color: #fff; }
        .status-printed { background-color: #198754; color: #fff; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="fas fa-print me-2"></i>Remote PDF Printing System</h3>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Upload PDF files (max 10MB) to print remotely on your home printer.
                        </div>
                        
                        <form id="uploadForm" class="needs-validation" novalidate>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="preview-container" id="previewContainer">
                                        <i class="fas fa-file-pdf fa-3x text-danger mb-3"></i>
                                        <p class="text-muted">Drag & drop your PDF file here or click to browse</p>
                                        <input type="file" class="form-control d-none" id="pdfFile" accept=".pdf" required>
                                        <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('pdfFile').click()">
                                            <i class="fas fa-folder-open me-2"></i>Browse Files
                                        </button>
                                        <div class="mt-3" id="fileInfo"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div id="pdfPreview" class="preview-container">
                                        <i class="fas fa-eye fa-3x text-secondary mb-3"></i>
                                        <p class="text-muted">PDF preview will appear here</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="paperSize" class="form-label">Paper Size</label>
                                    <select class="form-select" id="paperSize" required>
                                        <option value="A4" selected>A4 (210 × 297 mm)</option>
                                        <option value="A3">A3 (297 × 420 mm)</option>
                                        <option value="Letter">Letter (8.5 × 11 in)</option>
                                        <option value="Legal">Legal (8.5 × 14 in)</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="colorMode" class="form-label">Color Mode</label>
                                    <select class="form-select" id="colorMode" required>
                                        <option value="grayscale" selected>Grayscale</option>
                                        <option value="color">Color</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="pageRange" class="form-label">Page Range</label>
                                    <input type="text" class="form-control" id="pageRange" placeholder="e.g., 1-5, 1,3,5 or 'all'">
                                    <div class="form-text">Leave blank or type 'all' to print all pages</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="copies" class="form-label">Copies</label>
                                    <input type="number" class="form-control" id="copies" min="1" max="10" value="1" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="printer" class="form-label">Printer</label>
                                    <select class="form-select" id="printer">
                                        <option value="default">Default Printer</option>
                                        <option value="HP_LaserJet">HP LaserJet</option>
                                        <option value="EPSON_WF">Epson WorkForce</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="button" class="btn btn-secondary me-md-2" onclick="resetForm()">
                                    <i class="fas fa-redo me-2"></i>Reset
                                </button>
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="fas fa-upload me-2"></i>Upload & Print
                                </button>
                            </div>
                        </form>
                        
                        <div class="mt-4" id="uploadResult" style="display: none;">
                            <div class="alert alert-success">
                                <h5><i class="fas fa-check-circle me-2"></i>Print Job Created Successfully!</h5>
                                <div id="jobDetails"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Print Jobs</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="recentJobs">
                                <thead>
                                    <tr>
                                        <th>Job ID</th>
                                        <th>Filename</th>
                                        <th>Status</th>
                                        <th>Uploaded</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for QR Code -->
    <div class="modal fade" id="qrModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Print Job QR Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="qrImage" src="" alt="QR Code" class="img-fluid">
                    <p class="mt-3 text-muted">Scan to track this print job</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.14.305/pdf.min.js"></script>
    <script>
        let currentJobId = null;
        
        document.getElementById('pdfFile').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                handleFileSelect(file);
            }
        });
        
        function handleFileSelect(file) {
            if (file.type !== 'application/pdf') {
                alert('Please select a PDF file');
                return;
            }
            
            if (file.size > 10 * 1024 * 1024) {
                alert('File size exceeds 10MB limit');
                return;
            }
            
            document.getElementById('fileInfo').innerHTML = `
                <div class="alert alert-success">
                    <i class="fas fa-file-pdf me-2"></i>
                    <strong>${file.name}</strong><br>
                    <small>Size: ${formatFileSize(file.size)}</small>
                </div>
            `;
            
            previewPDF(file);
        }
        
        function previewPDF(file) {
            const fileReader = new FileReader();
            fileReader.onload = function() {
                const typedarray = new Uint8Array(this.result);
                pdfjsLib.getDocument(typedarray).promise.then(function(pdf) {
                    pdf.getPage(1).then(function(page) {
                        const canvas = document.createElement('canvas');
                        const context = canvas.getContext('2d');
                        const viewport = page.getViewport({ scale: 0.5 });
                        
                        canvas.height = viewport.height;
                        canvas.width = viewport.width;
                        
                        page.render({
                            canvasContext: context,
                            viewport: viewport
                        }).promise.then(function() {
                            document.getElementById('pdfPreview').innerHTML = '';
                            document.getElementById('pdfPreview').appendChild(canvas);
                        });
                    });
                }).catch(function(error) {
                    document.getElementById('pdfPreview').innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Could not preview PDF
                        </div>
                    `;
                });
            };
            fileReader.readAsArrayBuffer(file);
        }
        
        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('pdfFile');
            if (!fileInput.files[0]) {
                alert('Please select a PDF file');
                return;
            }
            
            const formData = new FormData();
            formData.append('file', fileInput.files[0]);
            formData.append('paper_size', document.getElementById('paperSize').value);
            formData.append('color_mode', document.getElementById('colorMode').value);
            formData.append('page_range', document.getElementById('pageRange').value || 'all');
            formData.append('copies', document.getElementById('copies').value);
            formData.append('printer_name', document.getElementById('printer').value);
            
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Uploading...';
            
            try {
                const response = await fetch('/api/print/upload', {
                    method: 'POST',
                    headers: {
                        'X-API-Key': '<?= config('Api')->key ?>'
                    },
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    currentJobId = result.data.job_id;
                    
                    document.getElementById('jobDetails').innerHTML = `
                        <p><strong>Job ID:</strong> ${result.data.job_id}</p>
                        <p><strong>Filename:</strong> ${result.data.filename}</p>
                        <p><strong>Status:</strong> <span class="badge status-pending">Pending</span></p>
                        <p><strong>Uploaded:</strong> ${result.data.uploaded_at}</p>
                        <button class="btn btn-sm btn-outline-primary mt-2" onclick="showQRCode('${result.data.qr_code}')">
                            <i class="fas fa-qrcode me-1"></i> Show QR Code
                        </button>
                        <button class="btn btn-sm btn-outline-secondary mt-2" onclick="checkStatus('${result.data.job_id}')">
                            <i class="fas fa-sync me-1"></i> Check Status
                        </button>
                    `;
                    
                    document.getElementById('uploadResult').style.display = 'block';
                    loadRecentJobs();
                    resetForm();
                } else {
                    alert('Upload failed: ' + result.message);
                }
            } catch (error) {
                alert('Upload error: ' + error.message);
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-upload me-2"></i>Upload & Print';
            }
        });
        
        function showQRCode(qrData) {
            document.getElementById('qrImage').src = qrData;
            new bootstrap.Modal(document.getElementById('qrModal')).show();
        }
        
        async function checkStatus(jobId) {
            try {
                const response = await fetch(`/api/print/status/${jobId}`, {
                    headers: {
                        'X-API-Key': '<?= config('Api')->key ?>'
                    }
                });
                
                const result = await response.json();
                alert(`Status: ${result.data.status_text}\nLast Update: ${result.data.completed_at || result.data.processed_at || result.data.uploaded_at}`);
            } catch (error) {
                alert('Error checking status: ' + error.message);
            }
        }
        
        async function loadRecentJobs() {
            try {
                const response = await fetch('/api/print/history?limit=5', {
                    headers: {
                        'X-API-Key': '<?= config('Api')->key ?>'
                    }
                });
                
                const result = await response.json();
                const tbody = document.querySelector('#recentJobs tbody');
                tbody.innerHTML = '';
                
                result.data.jobs.forEach(job => {
                    const statusClass = `status-${job.status_text.toLowerCase()}`;
                    const row = `
                        <tr>
                            <td><code>${job.job_id}</code></td>
                            <td>${job.filename}</td>
                            <td><span class="badge ${statusClass}">${job.status_text}</span></td>
                            <td>${new Date(job.uploaded_at).toLocaleString()}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-info" onclick="checkStatus('${job.job_id}')">
                                    <i class="fas fa-info-circle"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    tbody.innerHTML += row;
                });
            } catch (error) {
                console.error('Error loading recent jobs:', error);
            }
        }
        
        function resetForm() {
            document.getElementById('uploadForm').reset();
            document.getElementById('fileInfo').innerHTML = '';
            document.getElementById('pdfPreview').innerHTML = `
                <i class="fas fa-eye fa-3x text-secondary mb-3"></i>
                <p class="text-muted">PDF preview will appear here</p>
            `;
            document.getElementById('uploadResult').style.display = 'none';
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // Load recent jobs on page load
        document.addEventListener('DOMContentLoaded', loadRecentJobs);
        
        // Drag and drop functionality
        const previewContainer = document.getElementById('previewContainer');
        
        previewContainer.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        previewContainer.addEventListener('dragleave', function() {
            this.classList.remove('dragover');
        });
        
        previewContainer.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFileSelect(files[0]);
                document.getElementById('pdfFile').files = files;
            }
        });
    </script>
</body>
</html>
