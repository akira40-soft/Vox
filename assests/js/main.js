/**
 * Vox - Plataforma Electoral Angolana
 * Main JavaScript File
 *
 * Features:
 *   - Password visibility toggle
 *   - Form validation (email, password match, required fields)
 *   - Auto-dismiss alerts (5 seconds)
 *   - Real-time vote counting (fetch polling)
 *   - Copy to clipboard utility
 *   - Mobile sidebar toggle
 *   - Toast notification system
 *   - CSRF token handling
 *   - Loading spinner on form submit
 *   - Notifications polling
 *
 * Language: Portuguese (Angola)
 */

document.addEventListener('DOMContentLoaded', () => {
    initPasswordToggle();
    initFormValidation();
    initAutoDismissAlerts();
    initRealTimeResults();
    initMobileMenu();
    initSidebarToggle();
    initCopyToClipboard();
    initToastSystem();
    initLoginAnimation();
    initFormSubmissions();
    initRealtimeNotifications();
    initUserMenu();
});

/* ============================================
   Password Toggle
   ============================================ */
function initPasswordToggle() {
    document.querySelectorAll('.password-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const wrapper = btn.closest('.input-wrapper, .password-wrapper');
            if (!wrapper) return;
            const input = wrapper.querySelector('input[type="password"], input[type="text"]');
            if (!input) return;

            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';

            const svg = btn.querySelector('svg');
            if (svg) {
                if (isPassword) {
                    svg.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
                } else {
                    svg.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
                }
            }

            btn.setAttribute(
                'aria-label',
                isPassword ? 'Esconder password' : 'Mostrar password'
            );
        });
    });
}

/* ============================================
   Form Validation - CLIENT VALIDATION DISABLED
   ============================================ */
// Validação é feita 100% no backend PHP.
// Isto evita conflitos de cache e nomes de campos.
function initFormValidation() {
    console.log('Client-side validation disabled — PHP handles all validation');
}

function validateField(field) {
    if (field.required && !field.value.trim()) {
        showFieldError(field, 'Este campo e obrigatoria.');
        return false;
    }
    if (field.type === 'email' && field.value.trim() && !isValidEmail(field.value.trim())) {
        showFieldError(field, 'Por favor, insere um email valido.');
        return false;
    }
    if (field.type === 'password' && field.value && field.value.length < 6) {
        showFieldError(field, 'A password deve ter pelo menos 6 caracteres.');
        return false;
    }
    clearFieldError(field);
    return true;
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function showFieldError(input, message) {
    clearFieldError(input);
    input.style.borderColor = '#ef4444';
    const error = document.createElement('span');
    error.className = 'field-error';
    error.style.cssText = 'color: #ef4444; font-size: 0.8rem; display: block; margin-top: 4px;';
    error.textContent = message;
    input.closest('.form-group')?.appendChild(error);
}

function clearFieldError(input) {
    input.style.borderColor = '';
    const group = input.closest('.form-group');
    if (group) {
        const err = group.querySelector('.field-error');
        if (err) err.remove();
    }
}

/* ============================================
   Auto-dismiss Alerts
   ============================================ */
function initAutoDismissAlerts() {
    document.querySelectorAll('.alert.auto-dismiss, .alert:not(.alert-permanent)').forEach(alert => {
        // Close button
        const closeBtn = alert.querySelector('.alert-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => dismissAlert(alert));
        }
        const dismissBtn = alert.querySelector('.close-alert');
        if (dismissBtn) {
            dismissBtn.addEventListener('click', () => dismissAlert(alert));
        }

        setTimeout(() => {
            dismissAlert(alert);
        }, 5000);
    });
}

function dismissAlert(alert) {
    if (!alert || alert.classList.contains('dismissed')) return;
    alert.classList.add('dismissed');
    alert.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
    alert.style.opacity = '0';
    alert.style.transform = 'translateY(-10px)';
    setTimeout(() => {
        if (alert.parentNode) alert.remove();
    }, 300);
}

/* ============================================
   Real-time Results (Polling)
   ============================================ */
function initRealTimeResults() {
    const container = document.getElementById('live-results');
    if (!container) return;

    const salaId = container.dataset.salaId;
    if (!salaId) return;

    const refresh = () => {
        fetch(`api/results.php?sala_id=${encodeURIComponent(salaId)}&action=results`)
            .then(res => {
                if (!res.ok) throw new Error('Erro HTTP: ' + res.status);
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    renderLiveResults(data);
                }
            })
            .catch(err => console.warn('Erro ao buscar resultados:', err));
    };

    refresh();
    setInterval(refresh, 10000); // Update every 10 seconds
}

function renderLiveResults(data) {
    const container = document.getElementById('live-results');
    if (!container) return;

    if (data.resultados && data.resultados.length > 0) {
        container.innerHTML = data.resultados.map(tema => {
            const candidatos = (tema.candidatos || []).map(c =>
                `<div class="candidato-row">
                    <span class="cand-name">${escapeHtml(c.nome)}</span>
                    <div class="cand-bar-track">
                        <div class="cand-bar-fill" style="width: ${c.percentagem}%"></div>
                    </div>
                    <span class="cand-votes">${c.votos} votos (${c.percentagem}%)</span>
                </div>`
            ).join('');
            return `<div class="result-section">
                <h4>${escapeHtml(tema.tema_nome)}</h4>
                <p class="result-total">Total: ${tema.total_votos} votos</p>
                <div class="candidatos-list">${candidatos}</div>
            </div>`;
        }).join('');
    } else {
        container.innerHTML = '<p class="empty-state">Nenhum resultado disponivel ainda.</p>';
    }
}

/* Expose for inline usage */
window.updateVoteStats = function(salaId) {
    if (!salaId) return;
    fetch(`api/results.php?action=stats&sala_id=${encodeURIComponent(salaId)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const el1 = document.getElementById('totalVotos');
                const el2 = document.getElementById('totalVotantes');
                const el3 = document.getElementById('totalTemas');
                if (el1) el1.textContent = data.total_votos;
                if (el2) el2.textContent = data.total_votantes;
                if (el3) el3.textContent = data.temas_com_votos;
                showToast('Estatisticas atualizadas!', 'success');
            } else {
                showToast(data.message || 'Erro ao atualizar estatisticas.', 'error');
            }
        })
        .catch(() => showToast('Erro de conexao ao servidor.', 'error'));
};

/* ============================================
   Mobile Menu Toggle
   ============================================ */
function initMobileMenu() {
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;

    let menuBtn = document.querySelector('.menu-toggle');
    if (!menuBtn) {
        menuBtn = document.createElement('button');
        menuBtn.className = 'menu-toggle';
        menuBtn.innerHTML = '&#9776;';
        menuBtn.setAttribute('aria-label', 'Abrir menu');
        menuBtn.style.cssText = 'display:none;position:fixed;top:1rem;left:1rem;z-index:200;background:var(--blue);color:white;border:none;padding:0.75rem;border-radius:var(--radius-sm);font-size:1.25rem;cursor:pointer;';
        document.body.appendChild(menuBtn);
    }

    const checkMobile = () => {
        if (window.innerWidth <= 768) {
            menuBtn.style.display = 'block';
        } else {
            menuBtn.style.display = 'none';
            sidebar.classList.remove('mobile-open');
        }
    };
    checkMobile();
    window.addEventListener('resize', checkMobile);

    menuBtn.addEventListener('click', () => {
        sidebar.classList.toggle('mobile-open');
    });

    document.addEventListener('click', e => {
        if (window.innerWidth <= 768 && !sidebar.contains(e.target) && e.target !== menuBtn) {
            sidebar.classList.remove('mobile-open');
        }
    });
}

/* ============================================
   Sidebar Toggle (for gerir_sala style pages)
   ============================================ */
function initSidebarToggle() {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');

    if (!toggle || !sidebar) return;

    toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        sidebar.classList.toggle('open');
        toggle.classList.toggle('active');
        if (mainContent) mainContent.classList.toggle('shifted');
    });

    document.addEventListener('click', (e) => {
        if (sidebar.classList.contains('open') &&
            !sidebar.contains(e.target) &&
            !toggle.contains(e.target)) {
            sidebar.classList.remove('open');
            toggle.classList.remove('active');
            if (mainContent) mainContent.classList.remove('shifted');
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
            toggle.classList.remove('active');
            if (mainContent) mainContent.classList.remove('shifted');
        }
    });
}

/* ============================================
   Copy to Clipboard
   ============================================ */
function initCopyToClipboard() {
    document.querySelectorAll('[data-copy]').forEach(el => {
        el.addEventListener('click', () => {
            const text = el.dataset.copy;
            navigator.clipboard.writeText(text).then(() => {
                showToast('Copiado para a area de transferencia!', 'success');
            }).catch(() => {
                // Fallback for older browsers
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.left = '-9999px';
                document.body.appendChild(textarea);
                textarea.select();
                try {
                    document.execCommand('copy');
                    showToast('Copiado!', 'success');
                } catch (err) {
                    showToast('Erro ao copiar.', 'error');
                }
                document.body.removeChild(textarea);
            });
        });
    });
}

/**
 * Copy text programmatically and show a toast
 * @param {string} text - The text to copy
 */
window.copiarParaClipboard = function(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        return navigator.clipboard.writeText(text).then(() => true).catch(() => fallbackCopy(text));
    }
    return Promise.resolve(fallbackCopy(text));
};

function fallbackCopy(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();
    try {
        document.execCommand('copy');
        document.body.removeChild(textarea);
        return true;
    } catch (e) {
        if (textarea.parentNode) document.body.removeChild(textarea);
        return false;
    }
}

window.copiarComFeedback = function(text, successMsg) {
    window.copiarParaClipboard(text).then(success => {
        if (success) {
            showToast(successMsg || 'Texto copiado!', 'success');
        } else {
            showToast('Erro ao copiar.', 'error');
        }
    });
};

/* ============================================
   Toast Notification System (Enhanced)
   ============================================ */
function initToastSystem() {
    /**
     * Show a toast notification
     * @param {string} message - The toast message
     * @param {string} [type='info'] - Toast type: 'success' | 'error' | 'warning' | 'info'
     * @param {number} [duration=4000] - Duration in milliseconds
     */
    window.showToast = function(message, type = 'info', duration = 4000) {
        type = type || 'info';
        duration = duration || 4000;

        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.setAttribute('role', 'alert');

        const icons = {
            success: '&#10004;',
            error:   '&#10006;',
            warning: '&#9888;',
            info:    '&#8505;'
        };

        toast.innerHTML =
            `<span class="toast-icon">${icons[type] || icons.info}</span>` +
            `<span class="toast-message">${escapeHtml(message)}</span>` +
            `<button class="toast-close" aria-label="Fechar">&times;</button>`;

        container.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.add('show');
        });

        // Close button handler
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => removeToast(toast));

        // Auto dismiss
        setTimeout(() => {
            removeToast(toast);
        }, duration);
    };

    window.showSuccessToast = (msg) => window.showToast(msg, 'success');
    window.showErrorToast = (msg) => window.showToast(msg, 'error');

    function removeToast(toast) {
        if (!toast || toast.classList.contains('removing')) return;
        toast.classList.add('removing');
        toast.classList.remove('show');
        setTimeout(() => {
            if (toast.parentNode) toast.remove();
        }, 300);
    }
}

/* ============================================
   Login Animation
   ============================================ */
function initLoginAnimation() {
    const loginCard = document.querySelector('.login-card');
    if (loginCard) {
        loginCard.style.opacity = '0';
        loginCard.style.transform = 'translateY(20px)';
        loginCard.style.transition = 'all 0.5s ease';
        requestAnimationFrame(() => {
            loginCard.style.opacity = '1';
            loginCard.style.transform = 'translateY(0)';
        });
    }
}

/* ============================================
   Form Submissions (Loading state / Spinner)
   ============================================ */
function initFormSubmissions() {
    document.querySelectorAll('form:not([data-no-spinner])').forEach(form => {
        form.addEventListener('submit', e => {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (!submitBtn) return;
            if (form.classList.contains('disabled')) {
                e.preventDefault();
                return;
            }
            setLoading(submitBtn, true);
        });
    });
}

/**
 * Toggle loading state on a button with spinner
 * @param {HTMLElement} button
 * @param {boolean} loading
 */
window.setLoading = function(button, loading) {
    if (!button) return;
    if (loading) {
        button.classList.add('loading');
        button.disabled = true;
        const original = button.innerHTML || button.textContent;
        button.dataset.original = original;
        button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> A processar...';
    } else {
        button.classList.remove('loading');
        button.disabled = false;
        button.innerHTML = button.dataset.original || '';
        delete button.dataset.original;
    }
};

/* ============================================
   Real-time Notifications
   ============================================ */
let notifPollInterval = null;

function initRealtimeNotifications() {
    const bell = document.getElementById('notifToggle');
    const badge = document.getElementById('notifBadge');
    const dropdown = document.getElementById('notifDropdown');
    const notifList = document.getElementById('notiList');
    const markAllBtn = document.getElementById('markAllRead');

    // If no notification elements on this page, skip
    if (!badge) return;

    // Initial count fetch
    fetchNotifCount();

    // Start polling every 30 seconds
    notifPollInterval = setInterval(fetchNotifCount, 30000);

    // Toggle dropdown
    if (bell) {
        bell.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = dropdown && dropdown.style.display === 'block';
            if (isOpen && dropdown) {
                dropdown.style.display = 'none';
            } else {
                if (dropdown) dropdown.style.display = 'block';
                if (notifList) loadNotifications();
            }
        });
    }

    // Mark all as read
    if (markAllBtn) {
        markAllBtn.addEventListener('click', () => {
            const formData = new FormData();
            formData.append('csrf_token', getCSRFToken());
            formData.append('action', 'mark_all');

            fetch('api/notifications.php?action=mark_all', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (typeof updateBadgeUI === 'function') {
                        updateBadgeUI(0);
                    }
                    if (notifList) notifList.innerHTML = '<p class="notif-empty">Nenhuma notificação encontrada</p>';
                    showToast('Todas as notificações foram marcadas como lidas.', 'info');
                }
            })
            .catch(err => console.error('Erro ao marcar todas como lidas:', err));
        });
    }

    // Close dropdown on outside click
    document.addEventListener('click', (e) => {
        if (dropdown && dropdown.style.display === 'block' &&
            !dropdown.contains(e.target) &&
            bell !== e.target &&
            (!bell || !bell.contains(e.target))) {
            dropdown.style.display = 'none';
        }
    });
}

function fetchNotifCount() {
    fetch('api/notifications.php?action=count')
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            if (typeof updateBadgeUI === 'function') {
                updateBadgeUI(data.unread);
            }
        })
        .catch(err => console.warn('Erro ao verificar notificacoes:', err));
}

function loadNotifications() {
    const notifList = document.getElementById('notiList');
    if (!notifList) return;

    fetch('api/notifications.php?action=list&limit=10')
        .then(res => res.json())
        .then(data => {
            if (!data.success || !data.notifications.length) {
                notifList.innerHTML = '<p class="notif-empty">Nenhuma notificacao encontrada</p>';
                return;
            }

            let html = '';
            data.notifications.forEach(notif => {
                const isUnread = !notif.lida;
                const title = notif.tipo ? notif.tipo.charAt(0).toUpperCase() + notif.tipo.slice(1).replace('_', ' ') : 'Notificação';
                
                html += `<div class="dropdown-item notif-item ${isUnread ? 'unread' : ''}" data-id="${notif.id}" style="flex-direction: column; align-items: flex-start; gap: 4px; border-left: ${isUnread ? '3px solid var(--primary)' : 'none'};">
                    <div style="display: flex; justify-content: space-between; width: 100%;">
                        <strong style="font-size: 0.85rem; color: var(--text-header);">${escapeHtml(title)}</strong>
                        <span style="font-size: 0.7rem; color: var(--text-muted);">${escapeHtml(notif.relative_time)}</span>
                    </div>
                    <div style="font-size: 0.8rem; color: var(--text-main); line-height: 1.4;">${escapeHtml(notif.mensagem)}</div>
                    ${isUnread ? `<button class="notif-mark-read" data-id="${notif.id}" style="background: none; border: none; color: var(--primary); font-size: 0.7rem; font-weight: 700; padding: 0; cursor: pointer; margin-top: 4px;">Marcar como lida</button>` : ''}
                </div>`;
            });
            notifList.innerHTML = html;

            notifList.querySelectorAll('.notif-mark-read').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const item = this.closest('.notif-item');
                    markNotificationRead(id, item);
                });
            });
        })
        .catch(() => {
            notifList.innerHTML = '<p class="notif-empty">Erro ao carregar notificacoes</p>';
        });
}

function markNotificationRead(id, element) {
    const formData = new FormData();
    formData.append('csrf_token', getCSRFToken());
    formData.append('acao', 'mark_read');
    formData.append('notification_id', id);

    fetch('api/notifications.php?action=mark_read', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (element) {
                element.classList.remove('unread');
                element.style.borderLeft = 'none';
                const btn = element.querySelector('.notif-mark-read');
                if (btn) btn.remove();
            }
            
            if (typeof updateBadgeUI === 'function') {
                updateBadgeUI(data.unread);
            } else {
                const badge = document.getElementById('notifBadge');
                if (badge) {
                    if (data.unread > 0) {
                        badge.textContent = data.unread > 9 ? '9+' : data.unread;
                    } else {
                        badge.style.display = 'none';
                    }
                }
            }
        }
    })
    .catch(err => console.error('Erro ao marcar como lida:', err));
}

/* ============================================
   User Menu Toggle
   ============================================ */
function initUserMenu() {
    const toggle = document.getElementById('userMenuToggle');
    const dropdown = document.getElementById('userDropdown');

    if (!toggle || !dropdown) return;

    toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = dropdown.style.display === 'block';
        dropdown.style.display = isOpen ? 'none' : 'block';
    });

    document.addEventListener('click', (e) => {
        if (dropdown.style.display === 'block' &&
            !dropdown.contains(e.target) &&
            toggle !== e.target) {
            dropdown.style.display = 'none';
        }
    });
}

/* ============================================
   CSRF Token Handling
   ============================================ */
window.apiCall = async (endpoint, options = {}) => {
    const defaults = { 
        method: 'POST', 
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
        body: '', 
        loadingBtn: null, 
        showError: true 
    };
    options = { ...defaults, ...options };
    
    // Fetch does not allow body for GET/HEAD methods
    if (options.method === 'GET' || options.method === 'HEAD') {
        delete options.body;
        if (options.headers) delete options.headers['Content-Type'];
    }
    
    if (options.loadingBtn) window.setLoading(options.loadingBtn, true);
    
    const csrf = window.getCSRFToken();
    if (csrf && options.method === 'POST') {
        if (typeof options.body === 'string') {
            options.body += (options.body ? '&' : '') + `csrf_token=${encodeURIComponent(csrf)}`;
        } else if (options.body instanceof FormData) {
            options.body.append('csrf_token', csrf);
            // Browser must set multipart/form-data boundary automatically
            if (options.headers) delete options.headers['Content-Type'];
        } else if (options.body instanceof URLSearchParams) {
            options.body.append('csrf_token', csrf);
        }
    }
    
    try {
        const res = await fetch(endpoint, options);
        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.message || `Erro HTTP ${res.status}`);
        }
        return data;
    } catch (error) {
        console.error('API Error:', error);
        if (options.showError !== false) window.showErrorToast(error.message);
        throw error;
    } finally {
        if (options.loadingBtn) window.setLoading(options.loadingBtn, false);
    }
};

function getCSRFToken() {
    if (window.csrfToken) return window.csrfToken;
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.getAttribute('content');
    const input = document.querySelector('input[name="csrf_token"]');
    if (input) return input.value;
    return '';
}
window.getCSRFToken = getCSRFToken;
window.handleApiError = window.apiCall;

window.getCSRFParam = function() {
    return 'csrf_token=' + encodeURIComponent(getCSRFToken());
};

window.appendCSRF = function(formData) {
    formData.append('csrf_token', getCSRFToken());
    return formData;
};

window.addCSRFHeader = function(options) {
    options = options || {};
    if (!options.headers) options.headers = {};
    options.headers['X-CSRF-Token'] = getCSRFToken();
    return options;
};

/* ============================================
   Utility Functions
   ============================================ */
function escapeHtml(text) {
    if (typeof text !== 'string') return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

window.escapeHtml = escapeHtml;

// Confirm before dangerous actions
function confirmAction(message) {
    return window.confirm(message || 'Tem certeza que deseja continuar?');
}

window.confirmAction = confirmAction;

// Close all dropdowns
function closeAllDropdowns() {
    document.querySelectorAll('.notif-dropdown, .user-dropdown').forEach(dd => {
        dd.style.display = 'none';
    });
}

/* ============================================
   Cleanup on Page Unload
   ============================================ */
window.addEventListener('beforeunload', () => {
    if (notifPollInterval) {
        clearInterval(notifPollInterval);
    }
    if (window.statsPollingInterval) {
        clearInterval(window.statsPollingInterval);
    }
});
