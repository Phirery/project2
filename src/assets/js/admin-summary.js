(function () {
    'use strict';

    function markClickableAccessibility(root) {
        root.querySelectorAll('.summary-chip.is-clickable').forEach((chip) => {
            if (!chip.hasAttribute('role')) chip.setAttribute('role', 'button');
            if (!chip.hasAttribute('tabindex')) chip.setAttribute('tabindex', '0');
            if (chip.dataset.summaryBound === '1') return;

            chip.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    chip.click();
                }
            });

            chip.dataset.summaryBound = '1';
        });

        root.querySelectorAll('.summary-segment').forEach((seg) => {
            if (!seg.hasAttribute('tabindex')) seg.setAttribute('tabindex', '0');
            if (seg.dataset.summaryBound === '1') return;

            seg.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    seg.click();
                }
            });

            seg.dataset.summaryBound = '1';
        });
    }

    function refreshClearableFilters(root = document) {
        root.querySelectorAll('.filter-control-wrap').forEach((wrap) => {
            const control = wrap.querySelector('.form-control, .form-select');
            if (!control) return;
            const value = typeof control.value === 'string' ? control.value.trim() : control.value;
            wrap.classList.toggle('has-value', value !== '');
        });
    }

    function setupClearableFilters(root = document) {
        root.querySelectorAll('.filter-control-wrap').forEach((wrap) => {
            const control = wrap.querySelector('.form-control, .form-select');
            const clearBtn = wrap.querySelector('.filter-clear-btn');
            if (!control || !clearBtn) return;

            if (wrap.dataset.clearBound === '1') return;

            const onValueChange = () => refreshClearableFilters(root);
            control.addEventListener('input', onValueChange);
            control.addEventListener('change', onValueChange);

            clearBtn.addEventListener('click', () => {
                if (control.value === '') return;
                control.value = '';
                control.dispatchEvent(new Event('input', { bubbles: true }));
                control.dispatchEvent(new Event('change', { bubbles: true }));
                refreshClearableFilters(root);
                control.focus();
            });

            wrap.dataset.clearBound = '1';
        });
    }

    function setActive(selector, isActive) {
        document.querySelectorAll(selector).forEach((node) => {
            node.classList.toggle('is-active', !!isActive);
        });
    }

    function setActiveByDataset(selector, datasetKey, activeValue) {
        document.querySelectorAll(selector).forEach((node) => {
            node.classList.toggle('is-active', node.dataset[datasetKey] === activeValue);
        });
    }

    window.AdminSummary = {
        init(root = document) {
            markClickableAccessibility(root);
            setupClearableFilters(root);
            refreshClearableFilters(root);
        },
        setActive,
        setActiveByDataset,
        refreshClearableFilters
    };

    document.addEventListener('DOMContentLoaded', () => {
        window.AdminSummary.init();
    });
})();
