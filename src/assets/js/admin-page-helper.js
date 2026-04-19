(function () {
    'use strict';

    let actionsStyleInjected = false;
    let actionsMenuBound = false;
    let actionsMenuCounter = 0;
    let floatingActionsMenu = null;
    let activeActionsMenuId = '';

    const helper = {
        ensureActionsMenuStyles() {
            if (actionsStyleInjected || document.getElementById('admin-actions-menu-style')) return;

            const style = document.createElement('style');
            style.id = 'admin-actions-menu-style';
            style.textContent = `
                .admin-actions {
                    position: relative;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 500;
                }
                .admin-actions-toggle {
                    width: 34px;
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
                .admin-actions-toggle:hover {
                    background: #f4f8ff;
                    border-color: #bcd2f1;
                }
                .admin-actions-toggle i {
                    font-size: 0.9rem;
                    line-height: 1;
                    pointer-events: none;
                }
                .admin-actions-menu {
                    display: none;
                    min-width: 170px;
                    padding: 6px;
                    background: #fff;
                    border: 1px solid #dfe7f3;
                    border-radius: 10px;
                    box-shadow: 0 8px 22px rgba(16, 38, 74, 0.18);
                }
                .admin-actions-floating-menu {
                    position: fixed;
                    z-index: 501;
                }
                .admin-action-item {
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
                .admin-action-item:hover {
                    background: #f4f8ff;
                }
                .admin-action-item.is-view { color: #1d4ed8; }
                .admin-action-item.is-edit { color: #8b6508; }
                .admin-action-item.is-delete { color: #b42318; }
                .admin-action-item.is-lock { color: #b42318; }
                .admin-action-item.is-unlock { color: #0f766e; }
                .admin-action-item.is-toggle { color: #4f6b8a; }
                .custom-table tbody td {
                    overflow: visible;
                }
                .table-responsive {
                    overflow-x: auto !important;
                    overflow-y: visible !important;
                }
            `;
            document.head.appendChild(style);
            actionsStyleInjected = true;
        },
        bindActionsMenuEvents() {
            if (actionsMenuBound) return;

            document.addEventListener('click', (event) => {
                const target = event.target;
                if (!target.closest('.admin-actions') && !target.closest('.admin-actions-floating-menu')) {
                    helper.closeAllActionsMenus();
                }
            });

            window.addEventListener('resize', () => {
                helper.closeAllActionsMenus();
            });

            window.addEventListener('scroll', () => {
                helper.closeAllActionsMenus();
            }, true);

            // Đã gỡ bỏ listener show.bs.modal vì nó gây xung đột với việc mở modal từ async functions

            actionsMenuBound = true;
        },
        getFloatingActionsMenu() {
            if (floatingActionsMenu && document.body.contains(floatingActionsMenu)) return floatingActionsMenu;

            const menu = document.createElement('div');
            menu.className = 'admin-actions-menu admin-actions-floating-menu';
            menu.id = 'adminFloatingActionsMenu';
            menu.setAttribute('aria-hidden', 'true');
            document.body.appendChild(menu);
            floatingActionsMenu = menu;
            return menu;
        },
        closeAllActionsMenus() {
            document.querySelectorAll('.admin-actions.open').forEach(el => el.classList.remove('open'));

            const menu = helper.getFloatingActionsMenu();
            menu.style.display = 'none';
            menu.style.visibility = 'hidden';
            // Không xóa innerHTML để tránh lỗi Bootstrap Modal mất relatedTarget khi phần tử bị hủy
            menu.removeAttribute('data-source-menu-id');
            menu.setAttribute('aria-hidden', 'true');

            activeActionsMenuId = '';
        },
        toggleActionsMenu(menuId, event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }

            const menu = document.getElementById(menuId);
            if (!menu) return;

            const wrapper = menu.closest('.admin-actions');
            if (!wrapper) return;

            const shouldCloseCurrent = wrapper.classList.contains('open') && activeActionsMenuId === menuId;
            helper.closeAllActionsMenus();
            if (shouldCloseCurrent) return;

            wrapper.classList.add('open');
            activeActionsMenuId = menuId;
            helper.openFloatingActionsMenu(menu, wrapper);
        },
        openFloatingActionsMenu(sourceMenu, wrapper) {
            const floatingMenu = helper.getFloatingActionsMenu();
            floatingMenu.innerHTML = sourceMenu.innerHTML;
            floatingMenu.setAttribute('data-source-menu-id', sourceMenu.id || '');
            floatingMenu.setAttribute('aria-hidden', 'false');
            floatingMenu.style.visibility = 'hidden';
            floatingMenu.style.display = 'block';
            helper.positionActionsMenu(floatingMenu, wrapper);
            floatingMenu.style.visibility = 'visible';
        },
        positionActionsMenu(menu, wrapper) {
            const triggerRect = wrapper.getBoundingClientRect();
            const menuRect = menu.getBoundingClientRect();
            const menuWidth = menuRect.width || 170;
            const menuHeight = menuRect.height || 120;
            const viewportW = window.innerWidth;
            const viewportH = window.innerHeight;
            const gap = 6;
            const edgePadding = 8;

            let left = triggerRect.left - menuWidth - gap;
            if (left < edgePadding) {
                left = triggerRect.right + gap;
            }
            if (left + menuWidth > viewportW - edgePadding) {
                left = Math.max(edgePadding, viewportW - menuWidth - edgePadding);
            }

            let top = triggerRect.top;
            if (top + menuHeight > viewportH - edgePadding) {
                top = triggerRect.bottom - menuHeight;
            }
            top = Math.min(Math.max(edgePadding, top), Math.max(edgePadding, viewportH - menuHeight - edgePadding));

            menu.style.left = `${Math.round(left)}px`;
            menu.style.top = `${Math.round(top)}px`;
        },
        closeActionsMenu(menuId) {
            if (menuId && activeActionsMenuId && menuId !== activeActionsMenuId) return;
            helper.closeAllActionsMenus();
        },
        executeAction(codeStr) {
            helper.closeAllActionsMenus();
            setTimeout(() => {
                try {
                    window.eval(codeStr);
                } catch(e) {
                    console.error("Lỗi khi thực thi action:", e);
                }
            }, 50);
        },
        renderTableActions(actions = []) {
            const validActions = Array.isArray(actions) ? actions.filter(Boolean) : [];
            if (!validActions.length) return '';

            if (validActions.length === 1) {
                const action = validActions[0];
                const variantClass = action.variant ? ` btn-${action.variant}` : '';
                const iconClass = action.icon ? `fas ${action.icon}` : 'fas fa-circle';
                const title = String(action.label || 'Thao tác').replace(/"/g, '&quot;');
                return `<button class="btn-action${variantClass}" onclick="${action.onClick}" title="${title}"><i class="${iconClass}"></i></button>`;
            }

            helper.ensureActionsMenuStyles();
            helper.bindActionsMenuEvents();

            actionsMenuCounter += 1;
            const menuId = `adminActionsMenu_${actionsMenuCounter}`;

            const items = validActions.map((action) => {
                const variant = String(action.variant || '').replace(/^btn-/, '');
                const variantClass = variant ? ` is-${variant}` : '';
                const iconClass = action.icon ? `fas ${action.icon}` : 'fas fa-circle';
                const label = action.label || 'Thao tác';

                // Bọc lệnh thực thi qua hàm executeAction để tránh xung đột focus
                const safeCode = action.onClick.replace(/"/g, '\\"').replace(/'/g, "\\'");
                return `<button class="admin-action-item${variantClass}" type="button" onclick="window.AdminPageHelper.executeAction('${safeCode}')"><i class="${iconClass}"></i><span>${label}</span></button>`;
            }).join('');

            return `
                <div class="admin-actions">
                    <button class="admin-actions-toggle" type="button" title="Thao tác" onclick="window.AdminPageHelper.toggleActionsMenu('${menuId}', event)"><i class="fas fa-ellipsis-h"></i></button>
                    <div class="admin-actions-menu" id="${menuId}">
                        <div class="admin-actions-menu-inner">
                            ${items}
                        </div>
                    </div>
                </div>
            `;
        },
        formatDateToYYYYMMDD(date) {
            if (!date || isNaN(new Date(date).getTime())) return '';
            const d = new Date(date);
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }
    };

    window.AdminPageHelper = helper;
})();
