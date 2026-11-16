// Invoice Admin JavaScript Functions

// Lightweight alert helper for consistent UX and graceful fallback
function showAlert(message, type = 'info', timeout = 4000) {
    try {
        let container = document.getElementById('globalAlertContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'globalAlertContainer';
            container.style.position = 'fixed';
            container.style.top = '20px';
            container.style.left = '50%';
            container.style.transform = 'translateX(-50%)';
            container.style.zIndex = '9999';
            container.style.maxWidth = '640px';
            container.style.width = '90%';
            container.style.pointerEvents = 'none';
            document.body.appendChild(container);
        }
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-' + (type || 'info') + ' shadow';
        alertDiv.role = 'alert';
        alertDiv.style.pointerEvents = 'auto';
        alertDiv.innerHTML = '<div class="d-flex justify-content-between align-items-center"><div>' 
            + String(message) + 
            '</div><button type="button" class="btn-close" aria-label="Close"></button></div>';
        container.appendChild(alertDiv);
        const closeBtn = alertDiv.querySelector('.btn-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                alertDiv.remove();
                if (!container.children.length) container.remove();
            });
        }
        if (timeout > 0) {
            setTimeout(function () {
                alertDiv.classList.add('fade');
                alertDiv.style.opacity = '0';
                setTimeout(function () {
                    alertDiv.remove();
                    if (!container.children.length) container.remove();
                }, 300);
            }, timeout);
        }
    } catch (e) {
        try { alert(String(message)); } catch (_) {}
    }
}

// Utility function for debouncing
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Enhanced dropdown toggle functionality
class DropdownManager {
    constructor() {
        this.activeDropdowns = new Set();
        this.init();
    }

    init() {
        // Handle all dropdown toggles with custom data attribute
        document.addEventListener('click', (e) => {
            // Check if the clicked element or its parent has the data-custom-dropdown attribute
            const dropdownToggle = e.target.closest('[data-custom-dropdown]') || 
                                 (e.target.hasAttribute('data-custom-dropdown') ? e.target : null);
            
            if (dropdownToggle) {
                e.preventDefault();
                e.stopPropagation();
                this.toggleDropdown(dropdownToggle);
            } else {
                // Close all dropdowns when clicking outside
                this.closeAllDropdowns();
            }
        });

        // Handle escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeAllDropdowns();
            }
        });
    }

    toggleDropdown(toggle) {
        const dropdown = toggle.closest('.dropdown, .btn-group');
        if (!dropdown) {
            console.warn('No dropdown container found for toggle:', toggle);
            return;
        }

        const menu = dropdown.querySelector('.dropdown-menu');
        if (!menu) {
            console.warn('No dropdown menu found in container:', dropdown);
            return;
        }
        
        const isOpen = toggle.getAttribute('aria-expanded') === 'true';

        if (isOpen) {
            this.closeDropdown(toggle, dropdown, menu);
        } else {
            // Close other dropdowns first
            this.closeAllDropdowns();
            this.openDropdown(toggle, dropdown, menu);
        }
    }

    openDropdown(toggle, dropdown, menu) {
        toggle.setAttribute('aria-expanded', 'true');
        toggle.classList.add('show');
        menu.classList.add('show');
        dropdown.classList.add('show');
        this.activeDropdowns.add(dropdown);

        // Position dropdown to stay within viewport
        this.positionDropdown(toggle, menu);

        // Add visual indicator animation
        const icon = toggle.querySelector('i');
        if (icon && icon.classList.contains('fa-chevron-down')) {
            icon.style.transform = 'rotate(180deg)';
        }
    }

    positionDropdown(toggle, menu) {
        // Reset positioning
        menu.style.left = '';
        menu.style.right = '';
        menu.style.transform = '';

        // Get viewport and element dimensions
        const rect = toggle.getBoundingClientRect();
        const menuRect = menu.getBoundingClientRect();
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;

        // Check if dropdown would overflow right edge
        if (rect.left + menuRect.width > viewportWidth - 20) {
            menu.classList.add('dropdown-menu-end');
        } else {
            menu.classList.remove('dropdown-menu-end');
        }

        // Check if dropdown would overflow bottom edge
        if (rect.bottom + menuRect.height > viewportHeight - 20) {
            // Position above the button instead
            menu.style.top = 'auto';
            menu.style.bottom = '100%';
            menu.style.marginBottom = '0.25rem';
        } else {
            // Default positioning below the button
            menu.style.top = '';
            menu.style.bottom = '';
            menu.style.marginBottom = '';
        }
    }

    closeDropdown(toggle, dropdown, menu) {
        toggle.setAttribute('aria-expanded', 'false');
        toggle.classList.remove('show');
        menu.classList.remove('show');
        dropdown.classList.remove('show');
        this.activeDropdowns.delete(dropdown);

        // Reset visual indicator
        const icon = toggle.querySelector('i');
        if (icon && icon.classList.contains('fa-chevron-down')) {
            icon.style.transform = 'rotate(0deg)';
        }
    }

    closeAllDropdowns() {
        this.activeDropdowns.forEach(dropdown => {
            const toggle = dropdown.querySelector('[data-custom-dropdown]');
            const menu = dropdown.querySelector('.dropdown-menu');
            if (toggle && menu) {
                this.closeDropdown(toggle, dropdown, menu);
            }
        });
        this.activeDropdowns.clear();
    }
}

// Enhanced filter toggle with proper state management
function toggleFilterCollapse() {
    const filterCollapse = document.getElementById('filterCollapse');
    const filterToggleIcon = document.getElementById('filterToggleIcon');
    const isCollapsed = !filterCollapse.classList.contains('show');
    
    if (isCollapsed) {
        filterCollapse.classList.add('show');
        filterToggleIcon.classList.remove('fa-chevron-down');
        filterToggleIcon.classList.add('fa-chevron-up');
        filterToggleIcon.style.transform = 'rotate(180deg)';
    } else {
        filterCollapse.classList.remove('show');
        filterToggleIcon.classList.remove('fa-chevron-up');
        filterToggleIcon.classList.add('fa-chevron-down');
        filterToggleIcon.style.transform = 'rotate(0deg)';
    }
}

// Enhanced bulk selection with visual feedback
function checkBulkSelection() {
    const checked = document.querySelectorAll('.invoice-checkbox:checked');
    const allBoxes = document.querySelectorAll('.invoice-checkbox');
    const bulkActionBtn = document.getElementById('bulkActionBtn');
    const bulkActionsDropdown = document.getElementById('bulkActionsDropdown');
    const master = document.getElementById('selectAllCheckbox');
    
    // Update bulk action button visibility
    if (bulkActionBtn) {
        if (checked.length > 0) {
            bulkActionBtn.style.display = 'inline-block';
            bulkActionBtn.classList.add('btn-success');
            bulkActionBtn.classList.remove('btn-secondary');
        } else {
            bulkActionBtn.style.display = 'none';
            bulkActionBtn.classList.remove('btn-success');
            bulkActionBtn.classList.add('btn-secondary');
        }
    }
    
    // Update bulk actions dropdown button
    if (bulkActionsDropdown) {
        if (checked.length > 0) {
            bulkActionsDropdown.classList.add('btn-success');
            bulkActionsDropdown.classList.remove('btn-secondary');
            bulkActionsDropdown.innerHTML = `<i class="fas fa-tasks"></i> Bulk Actions (${checked.length})`;
        } else {
            bulkActionsDropdown.classList.remove('btn-success');
            bulkActionsDropdown.classList.add('btn-secondary');
            bulkActionsDropdown.innerHTML = '<i class="fas fa-tasks"></i> Bulk Actions';
        }
    }

    // Sync master checkbox state (checked / unchecked / indeterminate)
    if (master) {
        const total = allBoxes.length;
        const selected = checked.length;
        if (total === 0 || selected === 0) {
            master.indeterminate = false;
            master.checked = false;
        } else if (selected === total) {
            master.indeterminate = false;
            master.checked = true;
        } else {
            master.indeterminate = true;
            master.checked = false; // for accessibility, indeterminate implies mixed
        }
    }
}

// Selection helpers used by header controls and master checkbox
function selectAll() {
    try {
        const checkboxes = document.querySelectorAll('.invoice-checkbox');
        checkboxes.forEach(cb => cb.checked = true);
        const master = document.getElementById('selectAllCheckbox');
        if (master) master.checked = true;
        checkBulkSelection();
    } catch (e) {
        console.error('selectAll error:', e);
    }
}

function clearSelection() {
    try {
        const checkboxes = document.querySelectorAll('.invoice-checkbox');
        checkboxes.forEach(cb => cb.checked = false);
        const master = document.getElementById('selectAllCheckbox');
        if (master) master.checked = false;
        checkBulkSelection();
    } catch (e) {
        console.error('clearSelection error:', e);
    }
}

function toggleSelectAll() {
    try {
        const master = document.getElementById('selectAllCheckbox');
        const isChecked = master ? !!master.checked : false;
        const checkboxes = document.querySelectorAll('.invoice-checkbox');
        checkboxes.forEach(cb => cb.checked = isChecked);
        checkBulkSelection();
    } catch (e) {
        console.error('toggleSelectAll error:', e);
    }
}

// Enhanced bulk action with loading states
function performBulkAction(action) {
    const selectedInvoices = [];
    const checkboxes = document.querySelectorAll('.invoice-checkbox:checked');
    
    checkboxes.forEach(checkbox => {
        selectedInvoices.push(checkbox.value);
    });
    
    if (selectedInvoices.length === 0) {
        alert('Please select at least one invoice.');
        return;
    }
    
    const actionText = action.replace('_', ' ').toLowerCase();
    if (confirm(`Are you sure you want to ${actionText} the selected ${selectedInvoices.length} invoice(s)?`)) {
        // Add loading state to bulk actions button
        const bulkActionsBtn = document.getElementById('bulkActionsDropdown');
        if (bulkActionsBtn) {
            bulkActionsBtn.classList.add('loading');
            bulkActionsBtn.disabled = true;
        }
        
        // Create form and submit
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/invoices/admin/bulk-action';
        
        // Add CSRF token
        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = '_token';
        csrfToken.value = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        form.appendChild(csrfToken);
        
        // Add action
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = action;
        form.appendChild(actionInput);

        // Include current invoice type so the backend can map status values correctly
        const typeInput = document.createElement('input');
        typeInput.type = 'hidden';
        typeInput.name = 'type';
        const typeSelect = document.querySelector('select[name="type"]');
        typeInput.value = typeSelect ? typeSelect.value : 'agent';
        form.appendChild(typeInput);
        
        // Add selected invoices
        selectedInvoices.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'invoice_ids[]';
            input.value = id;
            form.appendChild(input);
        });
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Bulk send for client invoices only, with sequential progress and robust UX
async function performBulkSend() {
    try {
        // Ensure the current list is client invoices
        const urlParams = new URLSearchParams(window.location.search);
        const type = urlParams.get('type') || 'agent';
        if (type !== 'client') {
            showAlert('Bulk Send is available for Client Invoices only. Switch Type to "Client Invoices" and try again.', 'warning');
            return;
        }

        // Collect selected invoice IDs
        const ids = Array.from(document.querySelectorAll('.invoice-checkbox:checked')).map(cb => cb.value);
        if (!ids.length) {
            showAlert('Please select at least one client invoice to send.', 'warning');
            return;
        }

        if (!confirm(`Send ${ids.length} client invoice(s) now? Emails will be sent sequentially.`)) return;

        const bulkBtn = document.getElementById('bulkActionsDropdown');
        const originalHtml = bulkBtn ? bulkBtn.innerHTML : '';
        if (bulkBtn) {
            bulkBtn.disabled = true;
            bulkBtn.classList.add('loading');
            bulkBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Sending 0/' + ids.length;
        }

        // Helper to send a single invoice without spamming alerts
        const tokenEl = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : null;
        async function sendOne(id) {
            const res = await fetch(`/invoices/admin/client/${id}/send`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
                },
                body: JSON.stringify({})
            });
            if (!res.ok) {
                const text = await res.text();
                throw new Error(text || `HTTP ${res.status}`);
            }
            try {
                return await res.json();
            } catch (_) {
                return { success: true };
            }
        }

        let successCount = 0;
        const failed = [];
        for (let i = 0; i < ids.length; i++) {
            const id = ids[i];
            if (bulkBtn) bulkBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Sending ' + (i+1) + '/' + ids.length;
            try {
                await sendOne(id);
                successCount++;
                // Soft-highlight row state to indicate action succeeded without full reload
                const row = document.querySelector(`tr[data-invoice-id="${id}"]`);
                if (row) {
                    row.style.transition = 'background-color 0.6s ease';
                    row.style.backgroundColor = 'rgba(25, 135, 84, 0.08)'; // bootstrap success tint
                }
            } catch (err) {
                console.error('Bulk send error for', id, err);
                failed.push({ id, error: (err && err.message) ? err.message : 'Unknown error' });
                const row = document.querySelector(`tr[data-invoice-id="${id}"]`);
                if (row) {
                    row.style.transition = 'background-color 0.6s ease';
                    row.style.backgroundColor = 'rgba(220, 53, 69, 0.08)'; // bootstrap danger tint
                }
            }
        }

        // Summary
        if (failed.length === 0) {
            showAlert(`All ${successCount}/${ids.length} invoice(s) were sent successfully.`, 'success');
        } else {
            const failedList = failed.slice(0, 5).map(f => `#${f.id}`).join(', ');
            const moreNote = failed.length > 5 ? ` and ${failed.length - 5} more` : '';
            showAlert(`${successCount}/${ids.length} sent. Failed: ${failed.length} (${failedList}${moreNote}).`, 'warning');
        }

        // Restore button and refresh to reflect updated statuses
        if (bulkBtn) {
            bulkBtn.disabled = false;
            bulkBtn.classList.remove('loading');
            bulkBtn.innerHTML = '<i class="fas fa-tasks"></i> Bulk Actions';
        }

        // Reload to reflect status changes (sent -> badge update)
        setTimeout(() => {
            try { window.location.reload(); } catch (_) {}
        }, 800);
    } catch (e) {
        console.error('performBulkSend error:', e);
        showAlert('Unexpected error during bulk send. Please try again.', 'danger');
    }
}

// View invoice details
function viewDetails(invoiceId) {
    const urlParams = new URLSearchParams(window.location.search);
    const type = urlParams.get('type') || 'agent';
    
    let url;
    if (type === 'agent') {
        url = `/invoices/admin/agent/${invoiceId}`;
    } else {
        url = `/invoices/admin/client/${invoiceId}`;
    }
    
    window.open(url, '_blank');
}

// Edit invoice
function editInvoice(invoiceId) {
    const urlParams = new URLSearchParams(window.location.search);
    const type = urlParams.get('type') || 'agent';
    
    let url;
    if (type === 'agent') {
        url = `/invoices/agent/${invoiceId}/edit`;
    } else {
        url = `/invoices/client/${invoiceId}/edit`;
    }
    
    window.open(url, '_blank');
}

// Duplicate invoice
function duplicateInvoice(invoiceId) {
    if (confirm('Are you sure you want to duplicate this invoice?')) {
        const urlParams = new URLSearchParams(window.location.search);
        const type = urlParams.get('type') || 'agent';
        
        let url;
        if (type === 'agent') {
            url = `/invoices/agent/${invoiceId}/duplicate`;
        } else {
            url = `/invoices/client/${invoiceId}/duplicate`;
        }
        
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Invoice duplicated successfully!');
                location.reload();
            } else {
                alert('Error duplicating invoice: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while duplicating the invoice.');
        });
    }
}

// Delete invoice
function deleteInvoice(invoiceId) {
    if (confirm('Are you sure you want to delete this invoice? This action cannot be undone.')) {
        const urlParams = new URLSearchParams(window.location.search);
        const type = urlParams.get('type') || 'agent';
        
        let url;
        if (type === 'agent') {
            url = `/invoices/agent/${invoiceId}`;
        } else {
            url = `/invoices/client/${invoiceId}`;
        }
        
        fetch(url, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(async (response) => {
            let data = {};
            try {
                data = await response.json();
            } catch (_) {
                // non-JSON error (e.g., HTML 500 page)
                data = {};
            }

            if (!response.ok) {
                const msg = (data && (data.message || data.error)) || `${response.status} ${response.statusText}`;
                throw new Error(msg);
            }

            if (data.success) {
                alert('Invoice deleted successfully!');
                location.reload();
            } else {
                const msg = (data && data.message) || 'Unknown error';
                throw new Error(msg);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting invoice: ' + (error && error.message ? error.message : 'Unknown error'));
        });
    }
}

// Approve invoice
function approveInvoice(invoiceId, type) {
    if (confirm('Are you sure you want to approve this invoice?')) {
        const currentType = type || (document.querySelector('select[name="type"]') ? document.querySelector('select[name="type"]').value : 'agent');
        const url = currentType === 'client' ? `/invoices/client/${invoiceId}/approve` : `/invoices/agent/${invoiceId}/approve`;
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success || data.message) {
                alert('Invoice approved successfully!');
                location.reload();
            } else {
                alert('Error approving invoice: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while approving the invoice.');
        });
    }
}

function markPaidAgent(invoiceId) {
    if (!invoiceId) return;
    if (!confirm('Mark this agent invoice as paid?')) return;
    const tokenEl = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = tokenEl ? tokenEl.getAttribute('content') : null;
    fetch(`/invoices/agent/${invoiceId}/mark-paid`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
        }
    })
    .then(async (res) => {
        let data = {};
        try { data = await res.json(); } catch (_) {}
        if (!res.ok) {
            const msg = (data && (data.message || data.error)) || `HTTP ${res.status}`;
            throw new Error(msg);
        }
        return data;
    })
    .then((json) => {
        showAlert(json.message || 'Invoice marked as paid.', 'success');
        setTimeout(() => { try { window.location.reload(); } catch (_) {} }, 600);
    })
    .catch((err) => {
        console.error(err);
        showAlert(err && err.message ? err.message : 'Failed to mark invoice as paid.', 'danger');
    });
}

function markPaidClient(invoiceId) {
    if (!invoiceId) return;
    if (!confirm('Mark this client invoice as paid?')) return;
    const tokenEl = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = tokenEl ? tokenEl.getAttribute('content') : null;
    fetch(`/invoices/client/${invoiceId}/mark-paid`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
        }
    })
    .then(async (res) => {
        let data = {};
        try { data = await res.json(); } catch (_) {}
        if (!res.ok) {
            const msg = (data && (data.message || data.error)) || `HTTP ${res.status}`;
            throw new Error(msg);
        }
        return data;
    })
    .then((json) => {
        showAlert(json.message || 'Invoice marked as paid.', 'success');
        setTimeout(() => { try { window.location.reload(); } catch (_) {} }, 600);
    })
    .catch((err) => {
        console.error(err);
        showAlert(err && err.message ? err.message : 'Failed to mark invoice as paid.', 'danger');
    });
}

// Generate payslip
// Global variables for payslip modal
let currentInvoiceData = null;
let originalInvoiceData = null;
let auditTrail = [];

// Function to open payslip modal
function openPayslipModal(invoiceId) {
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('payslipModal'));
    modal.show();
    
    // Load invoice data
    loadInvoiceForPayslip(invoiceId);
}

// Function to load invoice data for payslip generation
function loadInvoiceForPayslip(invoiceId) {
    fetch(`/invoices/agent/${invoiceId}/payslip-data`)
        .then(async (response) => {
            let data;
            try {
                data = await response.json();
            } catch (_) {
                data = {};
            }

            if (!response.ok) {
                const msg = (data && (data.error || data.message)) || `HTTP ${response.status}`;
                throw new Error(msg);
            }

            // Support both legacy {success, invoice} and new {invoice, items} response shapes
            const invoice = data.invoice || (data.success ? (data.data && data.data.invoice) : null);
            const items = data.items || (data.success ? (data.data && data.data.items) : null);

            if (invoice) {
                // Attach items to invoice for downstream rendering
                if (!invoice.items) {
                    invoice.items = Array.isArray(items) ? items : [];
                }
                // Initialize current state
                currentInvoiceData = invoice;
                originalInvoiceData = JSON.parse(JSON.stringify(invoice)); // Deep copy
                renderPayslipModal(invoice);
            } else {
                const msg = (data && (data.error || data.message)) || 'Malformed response';
                showAlert('Error loading invoice data: ' + msg, 'danger');
                const el = document.getElementById('payslipModal');
                try {
                    const inst = (bootstrap && bootstrap.Modal)
                        ? (bootstrap.Modal.getInstance ? bootstrap.Modal.getInstance(el)
                           : (bootstrap.Modal.getOrCreateInstance ? bootstrap.Modal.getOrCreateInstance(el)
                              : new bootstrap.Modal(el)))
                        : null;
                    if (inst && typeof inst.hide === 'function') inst.hide();
                } catch (_) {}
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error loading invoice data: ' + (error && error.message ? error.message : ''), 'danger');
            const el = document.getElementById('payslipModal');
            try {
                const inst = (bootstrap && bootstrap.Modal)
                    ? (bootstrap.Modal.getInstance ? bootstrap.Modal.getInstance(el)
                       : (bootstrap.Modal.getOrCreateInstance ? bootstrap.Modal.getOrCreateInstance(el)
                          : new bootstrap.Modal(el)))
                    : null;
                if (inst && typeof inst.hide === 'function') inst.hide();
            } catch (_) {}
        });
}

// Function to render payslip modal content
function renderPayslipModal(invoice) {
    const modalBody = document.getElementById('payslipModalBody');
    
    const agentName = (invoice.agent && invoice.agent.user)
        ? (invoice.agent.user.fullName || [invoice.agent.user.first_name, invoice.agent.user.last_name].filter(Boolean).join(' '))
        : 'N/A';
    const createdText = invoice.created_at ? new Date(invoice.created_at).toLocaleDateString() : 'N/A';
    const totalDisplay = parseFloat(invoice.total_amount != null ? invoice.total_amount : (invoice.total != null ? invoice.total : 0)).toFixed(2);

    const itemsForWarn = (invoice.items || []);
    const missingDocItems = itemsForWarn.filter(i => {
        const jobType = i.job_type || (i.job && i.job.type) || '';
        const isTranslator = jobType.indexOf('TranslatorJob') !== -1;
        const docTop = i.agent_invoice_document || (i.documents && i.documents.agent_invoice) || null;
        const docNested = i.job && i.job.documents ? i.job.documents.agent_invoice : null;
        const hasDoc = !!((docTop && docTop.url) || (docNested && docNested.url));
        const adminAmt = parseFloat(i.admin_amount != null ? i.admin_amount : (i.amount != null ? i.amount : 0)) || 0;
        return isTranslator && !hasDoc && adminAmt > 0;
    });
    const warningHtml = missingDocItems.length > 0 ? (
        `<div class="alert alert-warning d-flex align-items-center" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <div>
                Some translator items have no personal invoice uploaded.
                You can still generate the payslip, but consider requesting the invoice from the translator.
            </div>
        </div>`
    ) : '';

    const html = `
        <div class="container-fluid">
            <!-- Invoice Header -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <h6 class="text-muted">Invoice Information</h6>
                    <p><strong>Invoice #:</strong> ${invoice.invoice_number || ''}</p>
                    <p><strong>Agent:</strong> ${agentName}</p>
                    <p><strong>Created:</strong> ${createdText}</p>
                </div>
                <div class="col-md-6">
                    <h6 class="text-muted">Payment Summary</h6>
                    <div class="row">
                        <div class="col-6">
                            <label class="form-label text-muted small">Agent Invoiced Amount</label>
                            <div class="form-control-plaintext fw-bold text-primary">£${totalDisplay}</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Admin Payable Amount</label>
                            <div class="form-control-plaintext fw-bold text-success">£<span id="payslipTotal">${totalDisplay}</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Job Management Section -->
            <div class="row mb-4">
                <div class="col-12">
                    ${warningHtml}
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-muted mb-0">Job Management</h6>
                        <button type="button" class="btn btn-outline-success btn-sm" onclick="showAddJobModal()">
                            <i class="fas fa-plus me-1"></i>Add Job
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="payslipJobsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Job Ref</th>
                                    <th>Client</th>
                                    <th>Postal Code</th>
                                    <th>Language</th>
                                    <th>Date</th>
                                    <th>Documents</th>
                                    <th>Amount</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${renderJobRows(invoice.items || [])}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Audit Trail -->
            <div class="row" id="auditTrailSection" style="display: none;">
                <div class="col-12">
                    <h6 class="text-muted">Changes Made</h6>
                    <div class="alert alert-info">
                        <ul id="auditTrailList" class="mb-0">
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    modalBody.innerHTML = html;
    
    // Initialize audit trail
    auditTrail = [];
    
    // Set up event listener for generate button
    const generateBtn = document.getElementById('generatePayslipBtn');
    if (generateBtn) {
        generateBtn.onclick = () => generatePayslip(invoice.id);
    }
}

// Function to render job rows
function renderJobRows(items) {
    return (items || []).map(item => {
        const uninvoicedClass = item.is_uninvoiced ? 'table-warning' : '';
        const uninvoicedBadge = item.is_uninvoiced ? '<span class="badge bg-warning text-dark ms-2">Uninvoiced</span>' : '';

        const jobRef = item.job_ref || (item.job && (item.job.ref || item.job.id)) || item.job_id || '';
        const clientName = item.client_name || (item.job ? item.job.client_name : '') || '';
        const postalCode = item.postal_code || (item.job ? item.job.postal : '') || '';
        const language = item.language || (item.job ? item.job.language : '') || '';
        const rawDate = item.date || (item.job ? item.job.date : '') || '';
        const dateText = rawDate ? new Date(rawDate).toLocaleDateString() : '';
        const agentAmt = parseFloat(item.agent_amount != null ? item.agent_amount : (item.amount != null ? item.amount : 0)).toFixed(2);
        const adminAmt = parseFloat(item.admin_amount != null ? item.admin_amount : (item.amount != null ? item.amount : 0)).toFixed(2);
        const jobIdAttr = item.job_id || (item.job ? item.job.id : '');

        // Determine job type and agent invoice doc
        const jobType = item.job_type || (item.job && item.job.type) || '';
        const docFromTop = item.agent_invoice_document || (item.documents && item.documents.agent_invoice) || null;
        const docFromNested = item.job && item.job.documents ? item.job.documents.agent_invoice : null;
        const agentInvoiceDoc = docFromTop || docFromNested || null;
        const hasAgentInvoiceDoc = !!(agentInvoiceDoc && agentInvoiceDoc.url);
        const isTranslatorJob = jobType.indexOf('TranslatorJob') !== -1;
        const documentsCellHtml = (() => {
            if (!isTranslatorJob) {
                return '<span class="text-muted">—</span>';
            }
            if (hasAgentInvoiceDoc) {
                const url = agentInvoiceDoc.url;
                const name = agentInvoiceDoc.name || 'Personal Invoice';
                return `
                    <a href="${url}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Open translator's personal invoice">
                        <i class="fas fa-receipt"></i>
                    </a>
                `;
            }
            return '<span class="badge bg-warning text-dark">No personal invoice</span>';
        })();
        
        return `
            <tr class="${uninvoicedClass}" data-item-id="${item.id}" data-job-id="${jobIdAttr}">
                <td>
                    <strong>${jobRef}</strong>${uninvoicedBadge}
                </td>
                <td>${clientName}</td>
                <td>${postalCode}</td>
                <td>${language}</td>
                <td>${dateText}</td>
                <td>${documentsCellHtml}</td>
                <td>
                    <div class="d-flex flex-column">
                        <div class="mb-1">
                            <small class="text-muted">Agent:</small> 
                            <span class="fw-bold text-primary">£${agentAmt}</span>
                        </div>
                        <div>
                            <small class="text-muted">Admin:</small>
                            <input type="number" 
                                   class="form-control form-control-sm d-inline-block admin-amount-input" 
                                   value="${adminAmt}" 
                                   step="0.01" 
                                   min="0"
                                   onchange="updateItemAdminAmount('${item.id}', this.value)"
                                   style="width: 80px;">
                        </div>
                    </div>
                </td>
                <td>
                    <button type="button" 
                            class="btn btn-sm btn-outline-danger" 
                            onclick="removeJob('${item.id}')"
                            title="Remove Job">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function updateItemAdminAmount(itemId, amount) {
    const item = currentInvoiceData.items.find(i => i.id == itemId);
    if (item) {
        const jobRef = item.job_ref || (item.job && (item.job.ref || item.job.id)) || item.job_id || item.id;
        const prev = (item.admin_amount != null ? item.admin_amount : (item.amount != null ? item.amount : 0));
        item.admin_amount = parseFloat(amount) || 0;
        
        // Add to audit trail
        addToAuditTrail(`Updated admin amount for job ${jobRef}: £${parseFloat(prev).toFixed(2)} → £${parseFloat(item.admin_amount).toFixed(2)}`);
        
        // Recalculate total
        recalculateTotal();
    }
}

function recalculateTotal() {
    const total = currentInvoiceData.items.reduce((sum, item) => {
        const val = (item.admin_amount != null ? item.admin_amount : (item.amount != null ? item.amount : 0));
        const num = parseFloat(val) || 0;
        return sum + num;
    }, 0);
    
    // Update the total display
    const totalElement = document.getElementById('payslipTotal');
    if (totalElement) {
        totalElement.textContent = total.toFixed(2);
    }
    
    // Update the current invoice data
    currentInvoiceData.admin_total_amount = total;
}

function removeJob(itemId) {
    if (confirm('Are you sure you want to remove this job from the payslip?')) {
        const itemIndex = currentInvoiceData.items.findIndex(i => i.id == itemId);
        if (itemIndex > -1) {
            const item = currentInvoiceData.items[itemIndex];
            currentInvoiceData.items.splice(itemIndex, 1);
            
            // Add to audit trail
            addToAuditTrail(`Removed job ${item.job_ref} (${item.client_name})`);
            
            // Re-render the table
            const tbody = document.querySelector('#payslipJobsTable tbody');
            tbody.innerHTML = renderJobRows(currentInvoiceData.items);
            
            // Recalculate total
            recalculateTotal();
        }
    }
}

// Function to show add job modal
function showAddJobModal() {
    const addJobHtml = `
        <div class="modal fade" id="addJobModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Job to Payslip</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Job ID or Reference</label>
                            <input type="text" class="form-control" id="jobIdInput" placeholder="Enter job ID or reference">
                            <div class="form-text">Enter the job ID or job reference number</div>
                        </div>
                        <div id="jobSearchResult" class="mt-3"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="searchJob()">Search Job</button>
                        <button type="button" class="btn btn-success" id="addJobBtn" onclick="addJobToPayslip()" style="display: none;">Add Job</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('addJobModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add new modal to body
    document.body.insertAdjacentHTML('beforeend', addJobHtml);
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('addJobModal'));
    modal.show();
}

// Function to search job
function searchJob() {
    const jobId = document.getElementById('jobIdInput').value.trim();
    if (!jobId) {
        showAlert('Please enter a job ID or reference', 'warning');
        return;
    }
    
    // Show loading
    document.getElementById('jobSearchResult').innerHTML = `
        <div class="text-center">
            <div class="spinner-border spinner-border-sm" role="status"></div>
            <span class="ms-2">Searching...</span>
        </div>
    `;
    
    fetch(`/jobs/search/${encodeURIComponent(jobId)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.job) {
                displayJobSearchResult(data.job);
            } else {
                document.getElementById('jobSearchResult').innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>Job not found
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('jobSearchResult').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-times me-2"></i>Error searching for job
                </div>
            `;
        });
}

// Function to display job search result
function displayJobSearchResult(job) {
    const resultDiv = document.getElementById('jobSearchResult');
    
    // Check if job has been invoiced (has amount)
    const isUninvoiced = !job.amount || job.amount === 0;
    const warningClass = isUninvoiced ? 'alert-warning' : 'alert-success';
    const warningMessage = isUninvoiced ? 
        '<div class="alert alert-warning mt-2 mb-0"><i class="fas fa-exclamation-triangle"></i> This job has not been invoiced by the agent</div>' : '';
    
    resultDiv.innerHTML = `
        <div class="alert ${warningClass}">
            <div class="row">
                <div class="col-md-6">
                    <strong>Job ID:</strong> ${job.id}<br>
                    <strong>Job Reference:</strong> ${job.job_ref}<br>
                    <strong>Client:</strong> ${job.client_name}
                </div>
                <div class="col-md-6">
                    <strong>Postal Code:</strong> ${job.postal_code}<br>
                    <strong>Language:</strong> ${job.language}<br>
                    <strong>Date:</strong> ${job.date}<br>
                    <strong>Amount:</strong> £${job.amount || '0.00'}
                </div>
            </div>
            ${warningMessage}
        </div>
    `;
    
    // Store the job data for adding
    window.selectedJob = job;
    
    // Show add button
    document.getElementById('addJobBtn').style.display = 'block';
}

function addJobToPayslip() {
    if (!window.selectedJob) {
        alert('No job selected');
        return;
    }
    
    const job = window.selectedJob;
    const isUninvoiced = !job.amount || job.amount === 0;
    
    // Check if job already exists in the payslip
    const existingRow = document.querySelector(`tr[data-job-id="${job.id}"]`);
    if (existingRow) {
        alert('This job is already in the payslip');
        return;
    }
    
    // Create new job item
    const newItem = {
        id: 'new_' + Date.now(),
        job_id: job.id,
        job_ref: job.job_ref,
        client_name: job.client_name,
        postal_code: job.postal_code,
        language: job.language,
        date: job.date,
        agent_amount: job.amount || 0,
        admin_amount: job.amount || 0,
        is_new: true,
        is_uninvoiced: isUninvoiced
    };
    
    // Add to current invoice data
    currentInvoiceData.items.push(newItem);
    
    // Re-render the job rows
    const tbody = document.querySelector('#payslipJobsTable tbody');
    tbody.innerHTML = renderJobRows(currentInvoiceData.items);
    
    // Recalculate totals
    recalculateTotal();
    
    // Add to audit trail
    const auditMessage = isUninvoiced ? 
        `Added uninvoiced job ${job.job_ref} (${job.client_name})` :
        `Added job ${job.job_ref} (${job.client_name}) - £${job.amount}`;
    addToAuditTrail(auditMessage);
    
    // Close the add job modal (robust across Bootstrap versions)
    const el = document.getElementById('addJobModal');
    try {
        const inst = (bootstrap && bootstrap.Modal)
            ? (bootstrap.Modal.getInstance ? bootstrap.Modal.getInstance(el)
               : (bootstrap.Modal.getOrCreateInstance ? bootstrap.Modal.getOrCreateInstance(el)
                  : new bootstrap.Modal(el)))
            : null;
        if (inst && typeof inst.hide === 'function') inst.hide();
    } catch (_) {}

    try {
        requestAnimationFrame(function () {
            const body = document.getElementById('payslipModalBody');
            if (body) {
                body.scrollTop = body.scrollHeight;
            }
        });
    } catch (_) {}
    
    // Clear search results
    document.getElementById('jobIdInput').value = '';
    document.getElementById('jobSearchResult').innerHTML = '';
    document.getElementById('addJobBtn').style.display = 'none';
    window.selectedJob = null;
}

// Function to add to audit trail
function addToAuditTrail(message) {
    auditTrail.push({
        timestamp: new Date(),
        message: message
    });
    
    // Show audit trail section
    const auditSection = document.getElementById('auditTrailSection');
    if (auditSection) {
        auditSection.style.display = 'block';
        
        const auditList = document.getElementById('auditTrailList');
        auditList.innerHTML = auditTrail.map(entry => 
            `<li>${entry.message} <small class="text-muted">(${entry.timestamp.toLocaleTimeString()})</small></li>`
        ).join('');
    }
}

// Function to save payslip
document.addEventListener('DOMContentLoaded', function() {
    const saveBtn = document.getElementById('savePayslipBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function() {
            if (!currentInvoiceData) return;
            
            // Prepare data for submission
            const payslipData = {
                invoice_id: currentInvoiceData.id,
                admin_total_amount: currentInvoiceData.admin_total_amount || currentInvoiceData.total_amount,
                items: currentInvoiceData.items.map(item => ({
                    id: item.id,
                    admin_amount: item.admin_amount || item.amount
                })),
                audit_trail: auditTrail
            };
            
            // Show loading state
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generating...';
            
            // Submit payslip
            fetch(`/invoices/agent/${currentInvoiceData.id}/generate-payslip`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(payslipData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Payslip generated successfully!', 'success');

                    // Persist minimal state for post-redirect UX
                    try {
                        const idFromUrl = (data.payslip_url && /\/payslips\/(\d+)/.exec(data.payslip_url)) ? /\/payslips\/(\d+)/.exec(data.payslip_url)[1] : '';
                        sessionStorage.setItem('lastPayslipId', data.payslip_id || idFromUrl || '');
                        sessionStorage.setItem('lastPayslipTotal', String(payslipData.admin_total_amount));
                    } catch (e) {}
                    
                    // Close modal and navigate to Payslip index page
                    const modalInst = bootstrap.Modal.getInstance(document.getElementById('payslipModal'));
                    if (modalInst) modalInst.hide();
                    window.location.assign('/payslips');
                } else {
                    showAlert('Error generating payslip: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error generating payslip', 'danger');
            })
            .finally(() => {
                // Reset button state
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save me-1"></i>Generate Payslip';
            });
        });
    }
});

function generatePayslip(invoiceId) {
    if (!currentInvoiceData) {
        alert('No payslip data available');
        return;
    }
    
    // Prepare the data for submission (align keys with backend)
    const payslipData = {
        invoice_id: invoiceId,
        admin_total_amount: (typeof currentInvoiceData.admin_total_amount !== 'undefined' && currentInvoiceData.admin_total_amount !== null)
            ? currentInvoiceData.admin_total_amount
            : (currentInvoiceData.total_amount != null ? currentInvoiceData.total_amount : currentInvoiceData.total),
        items: currentInvoiceData.items.map(item => ({
            id: item.id,
            job_id: item.job_id || (item.job ? item.job.id : null),
            job_ref: item.job_ref || (item.job ? (item.job.ref || item.job.id) : null),
            admin_amount: item.admin_amount != null ? item.admin_amount : (item.amount != null ? item.amount : 0),
            is_new: item.is_new || false,
            is_uninvoiced: item.is_uninvoiced || false
        })),
        audit_trail: auditTrail
    };
    
    // Show loading state
    const generateBtn = document.getElementById('generatePayslipBtn');
    const originalText = generateBtn ? generateBtn.innerHTML : '';
    if (generateBtn) {
        generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        generateBtn.disabled = true;
    }
    
    // Submit the payslip
    fetch(`/invoices/agent/${invoiceId}/generate-payslip`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify(payslipData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Persist context for post-redirect UX (optional highlight)
            try {
                const idFromUrl = (data.payslip_url && /\/payslips\/(\d+)/.exec(data.payslip_url)) ? /\/payslips\/(\d+)/.exec(data.payslip_url)[1] : '';
                sessionStorage.setItem('lastPayslipId', data.payslip_id || idFromUrl || '');
                sessionStorage.setItem('lastPayslipTotal', String(payslipData.admin_total_amount));
            } catch (e) {}
            
            // Close modal before navigation (robust across bootstrap versions)
            const el = document.getElementById('payslipModal');
            try {
                const inst = (bootstrap && bootstrap.Modal)
                    ? (bootstrap.Modal.getInstance ? bootstrap.Modal.getInstance(el)
                       : (bootstrap.Modal.getOrCreateInstance ? bootstrap.Modal.getOrCreateInstance(el)
                          : new bootstrap.Modal(el)))
                    : null;
                if (inst && typeof inst.hide === 'function') inst.hide();
            } catch (_) {}
            
            // Navigate to Payslip index page
            window.location.assign('/payslips');
        } else {
            showAlert('Error generating payslip: ' + (data.message || 'Unknown error'), 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error generating payslip: ' + (error && error.message ? error.message : ''), 'danger');
    })
    .finally(() => {
        // Restore button state
        if (generateBtn) {
            generateBtn.innerHTML = originalText;
            generateBtn.disabled = false;
        }
    });
}

// Enhanced document ready with comprehensive initialization
document.addEventListener('DOMContentLoaded', function() {
    // Disable Bootstrap's dropdown functionality for custom dropdowns
    document.querySelectorAll('[data-custom-dropdown]').forEach(function(element) {
        // Remove Bootstrap's data attributes to prevent conflicts
        element.removeAttribute('data-bs-toggle');
        element.removeAttribute('data-toggle');
    });
    
    // Initialize dropdown manager
    const dropdownManager = new DropdownManager();
    
    // Initialize filter toggle functionality
    const filterToggleBtn = document.querySelector('[data-bs-toggle="collapse"][data-bs-target="#filterCollapse"]');
    if (filterToggleBtn) {
        filterToggleBtn.addEventListener('click', debounce(toggleFilterCollapse, 300));
    }
    
    // Initialize checkbox change handlers
    const checkboxes = document.querySelectorAll('.invoice-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', checkBulkSelection);
    });
    
    // Initialize select all functionality
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const isChecked = this.checked;
            checkboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            checkBulkSelection();
        });
    }
    
    // Initialize button loading states
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        // Add click handler for loading state
        button.addEventListener('click', function(e) {
            // Skip for dropdown toggles and certain buttons
            if (this.classList.contains('dropdown-toggle') || 
                this.hasAttribute('data-bs-toggle') ||
                (this.type === 'button' && this.onclick)) {
                return;
            }

            // Only add loading state for actual form submissions or when explicitly requested
            const isSubmitButton = this.type === 'submit';
            const explicitDisable = this.getAttribute('data-disable-on-click') === 'true' || this.dataset.disableOnClick === '1';

            if (isSubmitButton || explicitDisable) {
                setTimeout(() => {
                    this.classList.add('loading');
                    this.disabled = true;
                }, 100);
            }
        });
    });
    
    // Initialize advanced filters button functionality
    const advancedFiltersBtn = document.querySelector('.btn-filter, [data-filter-toggle]');
    if (advancedFiltersBtn) {
        advancedFiltersBtn.addEventListener('click', debounce(function(e) {
            e.preventDefault();
            toggleFilterCollapse();
        }, 300));
    }
    
    // Initialize Bootstrap dropdowns if not using custom manager
    if (typeof bootstrap !== 'undefined') {
        // Disable Bootstrap's default dropdown behavior for custom managed dropdowns
        const customDropdowns = document.querySelectorAll('.dropdown-toggle[data-custom-dropdown]');
        customDropdowns.forEach(dropdown => {
            dropdown.removeAttribute('data-bs-toggle');
        });
    }
    
    // Initialize table row hover effects
    const tableRows = document.querySelectorAll('.invoice-row');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = 'rgba(0, 123, 255, 0.05)';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
    
    // Initialize keyboard navigation
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + A to select all
        if ((e.ctrlKey || e.metaKey) && e.key === 'a' && !e.target.matches('input, textarea')) {
            e.preventDefault();
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = true;
                selectAllCheckbox.dispatchEvent(new Event('change'));
            }
        }
        
        // Escape to clear selection
        if (e.key === 'Escape') {
            if (selectAllCheckbox && selectAllCheckbox.checked) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.dispatchEvent(new Event('change'));
            }
        }
    });
    
    // Initialize tooltips if Bootstrap is available (robust and idempotent)
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const options = { container: 'body', boundary: 'window', trigger: 'hover focus', delay: { show: 150, hide: 100 } };
        const tooltipNodes = Array.from(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipNodes.forEach(el => {
            try {
                const Tip = bootstrap.Tooltip;
                let inst = null;
                if (Tip.getInstance) {
                    inst = Tip.getInstance(el);
                }
                if (!inst) {
                    if (Tip.getOrCreateInstance) {
                        inst = Tip.getOrCreateInstance(el, options);
                    } else {
                        inst = new Tip(el, options);
                    }
                }
            } catch (e) {
                console && console.warn && console.warn('Tooltip init failed', e);
            }
        });
    }
    
    // Initialize performance monitoring
    console.log('Invoice Admin UI initialized successfully');

    // Check for any existing selections on page load
    checkBulkSelection();

    // Send Email modal helpers
    function getSendEmailModal() {
        const modal = document.getElementById('send-invoice-email-modal');
        const input = document.getElementById('send-email-to');
        const cc = document.getElementById('send-email-cc');
        const bcc = document.getElementById('send-email-bcc');
        const subject = document.getElementById('send-email-subject');
        const message = document.getElementById('send-email-message');
        const remember = document.getElementById('send-email-remember');
        const confirmBtn = document.getElementById('btn-send-email-confirm');
        const closeBtn = document.getElementById('btn-send-email-close');
        const cancelBtn = document.getElementById('btn-send-email-cancel');
        const sentSummary = document.getElementById('already-sent-summary');
        const sentAt = document.getElementById('already-sent-at');
        const title = document.getElementById('send-email-modal-title');
        return { modal, input, cc, bcc, subject, message, remember, confirmBtn, closeBtn, cancelBtn, sentSummary, sentAt, title };
    }

    function openSendEmailModal(invoiceId, defaultEmail, meta = {}) {
        const { modal, input, cc, bcc, subject, message, remember, confirmBtn, sentSummary, sentAt, title } = getSendEmailModal();
        if (!modal || !input || !confirmBtn) {
            // Fallback: if modal missing, send immediately with default (if any)
            return sendClientInvoice(invoiceId, { to_email: defaultEmail || null });
        }
        // Prefill
        if (typeof defaultEmail === 'string' && defaultEmail) {
            input.value = defaultEmail;
        }
        if (meta && meta.defaultSubject && subject) { subject.value = meta.defaultSubject; }
        if (cc) cc.value = '';
        if (bcc) bcc.value = '';
        if (message) message.value = '';
        if (remember) remember.checked = false;

        // Already sent summary
        const isSent = meta && (meta.status === 'sent');
        if (sentSummary) sentSummary.style.display = isSent ? 'block' : 'none';
        if (sentAt) sentAt.textContent = meta && meta.sentAt ? meta.sentAt : '';
        if (confirmBtn) confirmBtn.textContent = isSent ? 'Resend' : 'Send';
        if (title) title.textContent = isSent ? 'Resend Invoice Email' : 'Send Invoice Email';
        modal.style.display = 'block';

        function validateEmail(email) {
            // Simple RFC5322-like regex for frontend check; backend re-validates
            const re = /[^\s@]+@[^\s@]+\.[^\s@]+/;
            return re.test(String(email).toLowerCase());
        }

        function onConfirm() {
            const to = input.value.trim();
            if (!validateEmail(to)) {
                showAlert('Please enter a valid email address to send the invoice.', 'warning');
                input.focus();
                return;
            }
            const payload = {
                to_email: to,
                cc: cc ? cc.value.trim() : '',
                bcc: bcc ? bcc.value.trim() : '',
                subject: subject ? subject.value.trim() : '',
                message: message ? message.value : '',
                remember_email: remember ? !!remember.checked : false,
            };
            // Disable confirm while sending
            const original = confirmBtn.innerHTML;
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Sending...';
            sendClientInvoice(invoiceId, payload)
                .then(() => {
                    try { modal.style.display = 'none'; } catch (e) {}
                })
                .finally(() => {
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = original;
                });
        }

        // Bind once per open to avoid duplicate handlers
        confirmBtn.onclick = onConfirm;
        input.onkeydown = function(e){ if(e.key === 'Enter'){ e.preventDefault(); onConfirm(); } };
        setTimeout(() => { try { input.focus(); input.select(); } catch(e){} }, 50);
    }

    // Wire up send email buttons (header and footer) without inline JS
    function initSendButton(btn) {
        if (!btn) return;
        btn.addEventListener('click', async function(e) {
            e.preventDefault();
            const invoiceId = btn.getAttribute('data-invoice-id');
            if (!invoiceId) {
                showAlert('Missing invoice id.', 'danger');
                return;
            }
            const defaultEmail = (btn.dataset && btn.dataset.defaultEmail) ? btn.dataset.defaultEmail : undefined;
            const meta = {
                status: (btn.dataset && btn.dataset.status) ? btn.dataset.status : undefined,
                sentAt: (btn.dataset && btn.dataset.sentAt) ? btn.dataset.sentAt : undefined,
                defaultSubject: (btn.dataset && btn.dataset.defaultSubject) ? btn.dataset.defaultSubject : undefined,
            };
            openSendEmailModal(invoiceId, defaultEmail, meta);
        });
    }

    initSendButton(document.getElementById('btn-send-email'));
    initSendButton(document.getElementById('btn-send-mail-footer'));

    // Global delegation for row action buttons (no inline JS in Blade)
    document.addEventListener('click', function(e) {
        const btn = e.target && e.target.closest ? e.target.closest('[data-action][data-invoice-id]') : null;
        if (!btn) return;
        const action = btn.getAttribute('data-action');
        const invoiceId = btn.getAttribute('data-invoice-id');
        if (!invoiceId) return;

        switch (action) {
            case 'send-invoice':
                e.preventDefault();
                sendClientInvoice(invoiceId);
                break;
            case 'approve-invoice':
                e.preventDefault();
                approveInvoice(invoiceId, btn.getAttribute('data-invoice-type') || (document.querySelector('select[name="type"]') ? document.querySelector('select[name="type"]').value : 'agent'));
                break;
            case 'generate-payslip':
                e.preventDefault();
                openPayslipModal(invoiceId);
                break;
            case 'mark-paid':
                e.preventDefault();
                try {
                    const typeSelect = document.querySelector('select[name="type"]');
                    const currentType = typeSelect ? typeSelect.value : 'agent';
                    if (currentType === 'client') {
                        markPaidClient(invoiceId);
                    } else {
                        markPaidAgent(invoiceId);
                    }
                } catch (_) {
                    markPaidAgent(invoiceId);
                }
                break;
            case 'delete-invoice':
                e.preventDefault();
                deleteInvoice(invoiceId);
                break;
        }
    });
});


function sendClientInvoice(invoiceId, data) {
    try {
        if (!invoiceId) {
            showAlert('No invoice id provided.', 'warning');
            return;
        }
        const tokenEl = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : null;
        return fetch(`/invoices/admin/client/${invoiceId}/send`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
            },
            body: JSON.stringify((typeof data === 'string' || data instanceof String) ? { to_email: data } : (data || {}))
        })
        .then(async (res) => {
            if (!res.ok) {
                let message = `Failed to send invoice ${invoiceId}`;
                try {
                    const data = await res.json();
                    if (data && data.message) { message = data.message; }
                } catch(_) {
                    try { message = await res.text() || message; } catch(__) {}
                }
                throw new Error(message);
            }
            return res.json();
        })
        .then((json) => {
            showAlert(json.message || 'Invoice email sent successfully.', 'success');
            try {
                const row = document.querySelector(`tr[data-invoice-id="${invoiceId}"]`);
                if (row) {
                    const badge = row.querySelector('.badge');
                    if (badge) {
                        badge.textContent = 'Sent';
                        ['bg-secondary','bg-warning','bg-success','bg-danger','bg-info'].forEach(c => badge.classList.remove(c));
                        badge.classList.add('bg-info');
                    }
                    const sendBtn = row.querySelector('[data-action="send-invoice"]');
                    if (sendBtn) {
                        sendBtn.disabled = true;
                        sendBtn.setAttribute('title', 'Already sent');
                    }
                    const actions = row.querySelector('.btn-group.btn-group-sm');
                    if (actions && !row.querySelector('[data-action="mark-paid"]')) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'btn btn-outline-success btn-action';
                        btn.setAttribute('data-action', 'mark-paid');
                        btn.setAttribute('data-invoice-id', String(invoiceId));
                        btn.setAttribute('title', 'Mark as Paid');
                        btn.setAttribute('data-bs-toggle', 'tooltip');
                        btn.innerHTML = '<i class="fas fa-money-bill"></i> Mark Paid';
                        actions.appendChild(btn);
                    }
                } else {
                    const headerBtn = document.getElementById('btn-send-email');
                    const footerBtn = document.getElementById('btn-send-mail-footer');
                    const now = new Date();
                    const pad = (n) => (n < 10 ? ('0' + n) : String(n));
                    const stamp = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}`;
                    if (headerBtn && headerBtn.dataset) {
                        headerBtn.dataset.status = 'sent';
                        headerBtn.dataset.sentAt = stamp;
                    }
                    if (footerBtn && footerBtn.dataset) {
                        footerBtn.dataset.status = 'sent';
                        footerBtn.dataset.sentAt = stamp;
                    }
                }
            } catch (_) {}
            return json;
        })
        .catch((err) => {
            console.error(err);
            showAlert(err && err.message ? err.message : 'Failed to send invoice email. Please try again or check logs.', 'danger');
        });
    } catch (e) {
        console.error(e);
        showAlert('Unexpected error while sending invoice.', 'danger');
    }
}

// Expose key functions for inline handlers in Blade templates
try {
    Object.assign(window, {
        // bulk and selection
        performBulkAction,
        performBulkSend,
        selectAll,
        clearSelection,
        toggleSelectAll,
        // single invoice actions
        sendClientInvoice,
        viewDetails,
        editInvoice,
        duplicateInvoice,
        deleteInvoice,
        approveInvoice,
        markPaidClient,
        // payslip and job modal actions
        openPayslipModal,
        searchJob,
        addJobToPayslip,
        generatePayslip
    });
} catch (e) {
    // Non-fatal: still usable via global scope in most browsers
}
