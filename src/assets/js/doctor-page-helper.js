(function () {
    'use strict';

    let actionsStyleInjected = false;
    let actionsMenuBound = false;
    let actionsMenuCounter = 0;

    const endpoints = window.API_ENDPOINTS || {};
    const helper = {
        componentPath: 'components/',
        doctorApiBase: endpoints.doctor || '',
        authApiBase: endpoints.auth || '',
        withDoctorCredentials(options = {}) {
            return { ...options, credentials: 'include' };
        },
        async fetchDoctorJson(urlOrPath, options = {}) {
            const url = /^https?:\/\//i.test(urlOrPath) || String(urlOrPath).startsWith('/')
                ? urlOrPath
                : `${helper.doctorApiBase}/${String(urlOrPath).replace(/^\/+/, '')}`;

            const response = await fetch(url, helper.withDoctorCredentials(options));
            return response.json();
        },
        ensureSharedUiStyles() {
            if (document.getElementById('doctor-shared-ui-style')) return;
            const style = document.createElement('style');
            style.id = 'doctor-shared-ui-style';
            style.textContent = `
                .filter-control-wrap {
                    position: relative;
                }
                .filter-control-wrap .form-control {
                    padding-right: 2.2rem;
                }
                .filter-clear-btn {
                    position: absolute;
                    top: 50%;
                    right: 0.55rem;
                    transform: translateY(-50%);
                    width: 1.35rem;
                    height: 1.35rem;
                    border: none;
                    background: transparent;
                    color: #9aa4b2;
                    display: none;
                    align-items: center;
                    justify-content: center;
                    border-radius: 999px;
                    cursor: pointer;
                }
                .filter-clear-btn:hover {
                    background: rgba(79, 107, 138, 0.08);
                    color: #4f6b8a;
                }
                .filter-clear-btn.visible {
                    display: inline-flex;
                }
                .table-header-actions {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-shrink: 0;
                }
                .sort-toggle-btn {
                    width: 40px;
                    height: 40px;
                    border-radius: 10px;
                    border: 1px solid #d7e1ee;
                    background: #fff;
                    color: #4f6b8a;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    transition: all .2s ease;
                }
                .sort-toggle-btn:hover {
                    background: #f3f8ff;
                    border-color: #bcd2f1;
                }
            `;
            document.head.appendChild(style);
        },
        ensureActionsMenuStyles() {
            if (actionsStyleInjected || document.getElementById('doctor-actions-menu-style')) return;
            helper.ensureSharedUiStyles();
            const style = document.createElement('style');
            style.id = 'doctor-actions-menu-style';
            style.textContent = `
                .doctor-actions {
                    position: relative;
                    display: inline-flex;
                    align-items: center;
                    z-index: 4000;
                }
                .doctor-actions-toggle {
                    width: 36px;
                    height: 34px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 8px;
                    border: 1px solid #d7e1ee;
                    background: #fff;
                    color: #4f6b8a;
                    cursor: pointer;
                    transition: all .2s ease;
                }
                .doctor-actions-toggle i {
                    font-size: 0.95rem;
                    line-height: 1;
                    pointer-events: none;
                }
                .doctor-actions-toggle:hover {
                    background: #f3f8ff;
                    border-color: #bcd2f1;
                }
                .doctor-actions-menu {
                    display: none;
                    position: absolute;
                    top: 0;
                    right: calc(100% + 6px);
                    z-index: 6000;
                    min-width: 160px;
                    padding: 6px;
                    background: #fff;
                    border: 1px solid #dfe7f3;
                    border-radius: 10px;
                    box-shadow: 0 8px 22px rgba(16, 38, 74, 0.18);
                }
                .doctor-actions-menu.menu-up {
                    top: auto;
                    bottom: 0;
                }
                .doctor-actions-menu.menu-down {
                    top: 0;
                    bottom: auto;
                }
                .doctor-actions.open .doctor-actions-menu {
                    display: block;
                }
                .custom-table tbody td {
                    overflow: visible;
                }
                .table-responsive {
                    overflow-x: auto !important;
                    overflow-y: visible !important;
                }
                .doctor-action-item {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    width: 100%;
                    border: none;
                    background: transparent;
                    padding: 8px 10px;
                    border-radius: 8px;
                    color: #334;
                    font-size: 0.86rem;
                    cursor: pointer;
                    white-space: nowrap;
                    text-align: left;
                }
                .doctor-action-item:hover {
                    background: #f4f8ff;
                }
                .doctor-action-item.is-delete { color: #b42318; }
                .doctor-action-item.is-cancel { color: #b42318; }
                .doctor-action-item.is-complete { color: #0f766e; }
                .doctor-action-item.is-view { color: #1d4ed8; }
            `;
            document.head.appendChild(style);
            actionsStyleInjected = true;
        },
        syncSearchClearButton(inputId, buttonId) {
            helper.ensureSharedUiStyles();
            const input = document.getElementById(inputId);
            const button = document.getElementById(buttonId);
            if (!input || !button) return;
            button.classList.toggle('visible', Boolean(String(input.value || '').trim()));
        },
        clearSearchInput(inputId, buttonId, callbackName) {
            const input = document.getElementById(inputId);
            if (!input) return;
            input.value = '';
            helper.syncSearchClearButton(inputId, buttonId);
            if (callbackName && typeof window[callbackName] === 'function') {
                window[callbackName]();
            }
        },
        bindActionsMenuEvents() {
            if (actionsMenuBound) return;
            document.addEventListener('click', (event) => {
                const target = event.target;
                if (!target.closest('.doctor-actions')) {
                    document.querySelectorAll('.doctor-actions.open').forEach(el => el.classList.remove('open'));
                }
            });
            window.addEventListener('resize', () => {
                document.querySelectorAll('.doctor-actions.open').forEach(el => el.classList.remove('open'));
            });
            window.addEventListener('scroll', () => {
                document.querySelectorAll('.doctor-actions.open').forEach(el => el.classList.remove('open'));
            }, true);
            actionsMenuBound = true;
        },
        toggleActionsMenu(menuId, event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            const menu = document.getElementById(menuId);
            if (!menu) return;
            const wrapper = menu.closest('.doctor-actions');
            if (!wrapper) return;

            const shouldOpen = !wrapper.classList.contains('open');
            document.querySelectorAll('.doctor-actions.open').forEach(el => el.classList.remove('open'));
            if (!shouldOpen) return;

            wrapper.classList.add('open');
            helper.positionActionsMenu(menu, wrapper);
        },
        positionActionsMenu(menu, wrapper) {
            // Measure menu while hidden from user
            menu.style.visibility = 'hidden';
            menu.style.display = 'block';

            const triggerRect = wrapper.getBoundingClientRect();
            const menuRect = menu.getBoundingClientRect();
            const menuHeight = menuRect.height || 120;
            const viewportH = window.innerHeight;

            // Auto direction: down first if enough space, otherwise up.
            const spaceBelow = viewportH - triggerRect.bottom;
            const spaceAbove = triggerRect.top;
            const openUp = spaceBelow < menuHeight + 8 && spaceAbove > spaceBelow;
            menu.classList.toggle('menu-up', openUp);
            menu.classList.toggle('menu-down', !openUp);

            menu.style.visibility = 'visible';
            menu.style.display = '';
        },
        closeActionsMenu(menuId) {
            const menu = document.getElementById(menuId);
            const wrapper = menu ? menu.closest('.doctor-actions') : null;
            if (wrapper) wrapper.classList.remove('open');
        },
        renderTableActions(actions = []) {
            const validActions = Array.isArray(actions) ? actions.filter(Boolean) : [];
            if (!validActions.length) return '';

            if (validActions.length === 1) {
                const action = validActions[0];
                const variantClass = action.variant ? ` btn-${action.variant}` : '';
                const iconClass = action.icon ? `fas ${action.icon}` : 'fas fa-play';
                const title = String(action.label || 'Thao tác').replace(/"/g, '&quot;');
                return `<button class="btn-action${variantClass}" onclick="${action.onClick}" title="${title}"><i class="${iconClass}"></i></button>`;
            }

            helper.ensureActionsMenuStyles();
            helper.bindActionsMenuEvents();

            actionsMenuCounter += 1;
            const menuId = `doctorActionsMenu_${actionsMenuCounter}`;

            const items = validActions.map((action) => {
                const variant = String(action.variant || '').replace(/^btn-/, '');
                const variantClass = variant ? ` is-${variant}` : '';
                const iconClass = action.icon ? `fas ${action.icon}` : 'fas fa-play';
                return `<button class="doctor-action-item${variantClass}" onclick="${action.onClick}; window.DoctorPageHelper.closeActionsMenu('${menuId}');"><i class="${iconClass}"></i><span>${action.label || 'Thao tác'}</span></button>`;
            }).join('');

            return `
                <div class="doctor-actions">
                    <button class="doctor-actions-toggle" type="button" title="Thao tác" onclick="window.DoctorPageHelper.toggleActionsMenu('${menuId}', event)"><i class="fas fa-ellipsis-h"></i></button>
                    <div class="doctor-actions-menu" id="${menuId}">
                        ${items}
                    </div>
                </div>
            `;
        }
    };

    window.DoctorPageHelper = helper;

    // Backward-compatible globals for existing doctor pages/scripts.
    window.COMPONENT_PATH = window.COMPONENT_PATH || helper.componentPath;
    window.API_BASE_DOCTOR = window.API_BASE_DOCTOR || helper.doctorApiBase;
    window.API_BASE_AUTH = window.API_BASE_AUTH || helper.authApiBase;
    window.DOCTOR_INFO = window.DOCTOR_INFO || {};
})();
