document.addEventListener('DOMContentLoaded', function () {
    function ensureSwal() {
        if (window.Swal) {
            return Promise.resolve(window.Swal);
        }

        return new Promise(function (resolve, reject) {
            const existing = document.querySelector('script[data-swal-lib="1"]');
            if (existing) {
                existing.addEventListener('load', function () {
                    resolve(window.Swal);
                });
                existing.addEventListener('error', reject);
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
            script.setAttribute('data-swal-lib', '1');
            script.onload = function () {
                resolve(window.Swal);
            };
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatFlashMessageHtml(message) {
        const normalized = String(message || '').trim();
        if (normalized === '') {
            return '';
        }

        const parts = normalized
            .replace(/\.\s+(?=[A-Z][a-z]+\s*:\s*)/g, '.|')
            .split(/\s*,\s*|\|/)
            .filter(function (part) {
                return part.length > 0;
            })
            .map(function (part) {
                return part.trim();
            });

        if (parts.length <= 1) {
            return escapeHtml(normalized);
        }

        return parts
            .map(function (part) {
                return escapeHtml(part);
            })
            .join('<br>');
    }

    const flashPayload = document.querySelector('[data-admin-flash="1"]');
    if (flashPayload && window.Swal) {
        const flashType = (flashPayload.getAttribute('data-admin-flash-type') || 'success').toLowerCase();
        const flashMessage = flashPayload.getAttribute('data-admin-flash-message') || '';
        const isError = flashType === 'error';

        Swal.fire({
            icon: isError ? 'error' : 'success',
            title: isError ? 'Action Failed' : 'Success',
            html: formatFlashMessageHtml(flashMessage),
            confirmButtonText: 'OK',
            buttonsStyling: false,
            scrollbarPadding: false,
            customClass: {
                popup: 'admin-swal-popup',
                title: 'admin-swal-title',
                htmlContainer: 'admin-swal-text',
                confirmButton: 'admin-swal-confirm'
            }
        });
    }

    document.querySelectorAll('[data-confirm-logout="admin"]').forEach(function (link) {
        link.addEventListener('click', async function (event) {
            event.preventDefault();

            const logoutUrl = link.getAttribute('href');
            if (!logoutUrl) {
                return;
            }

            try {
                const Swal = await ensureSwal();
                const result = await Swal.fire({
                    icon: 'question',
                    title: 'Logout?',
                    text: 'Are you sure you want to logout?',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Logout',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true,
                    buttonsStyling: false,
                    scrollbarPadding: false,
                    customClass: {
                        popup: 'admin-swal-popup',
                        title: 'admin-swal-title',
                        htmlContainer: 'admin-swal-text',
                        confirmButton: 'admin-swal-confirm',
                        cancelButton: 'admin-swal-cancel'
                    }
                });

                if (result.isConfirmed) {
                    window.location.href = logoutUrl;
                }
            } catch (error) {
                console.error('Failed to load SweetAlert for logout confirmation:', error);
            }
        });
    });

    const navToggle = document.querySelector('[data-admin-nav-toggle]');
    const navLinks = document.querySelector('[data-admin-nav-links]');

    if (navToggle && navLinks) {
        function closeNavMenu() {
            navLinks.classList.remove('open');
            navToggle.setAttribute('aria-expanded', 'false');
        }

        navToggle.addEventListener('click', function () {
            const isOpen = navLinks.classList.toggle('open');
            navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        navLinks.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 920) {
                    closeNavMenu();
                }
            });
        });

        document.addEventListener('click', function (event) {
            if (window.innerWidth > 920) {
                return;
            }

            if (!navLinks.classList.contains('open')) {
                return;
            }

            if (navToggle.contains(event.target) || navLinks.contains(event.target)) {
                return;
            }

            closeNavMenu();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeNavMenu();
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 920) {
                closeNavMenu();
            }
        });
    }

    const evalToggle = document.querySelector('[data-admin-toggle-logs]');
    if (evalToggle) {
        evalToggle.addEventListener('click', function () {
            const targetId = evalToggle.getAttribute('data-target') || 'admin-eval-logs';
            const target = document.getElementById(targetId);
            if (!target) {
                return;
            }

            const shouldOpen = target.hasAttribute('hidden');
            if (shouldOpen) {
                target.removeAttribute('hidden');
            } else {
                target.setAttribute('hidden', 'hidden');
            }

            const openLabel = evalToggle.getAttribute('data-open-label') || 'Hide Detailed Logs';
            const closedLabel = evalToggle.getAttribute('data-closed-label') || 'View Detailed Logs';
            const icon = evalToggle.querySelector('i');
            evalToggle.textContent = shouldOpen ? openLabel : closedLabel;
            if (icon) {
                evalToggle.prepend(icon);
                evalToggle.insertBefore(document.createTextNode(' '), icon.nextSibling);
            }
        });
    }

    const usersDeleteForm = document.querySelector('[data-users-delete-form]');
    if (usersDeleteForm) {
        const selectAllUsersCheckbox = usersDeleteForm.querySelector('[data-users-select-all]');
        const userRowCheckboxes = Array.prototype.slice.call(
            usersDeleteForm.querySelectorAll('[data-user-row-checkbox]')
        );

        function getSelectedUsersCount() {
            return userRowCheckboxes.filter(function (checkbox) {
                return checkbox.checked;
            }).length;
        }

        function syncSelectAllState() {
            if (!selectAllUsersCheckbox) {
                return;
            }

            const selectedCount = getSelectedUsersCount();
            const totalCount = userRowCheckboxes.length;
            selectAllUsersCheckbox.checked = totalCount > 0 && selectedCount === totalCount;
            selectAllUsersCheckbox.indeterminate = selectedCount > 0 && selectedCount < totalCount;
        }

        if (selectAllUsersCheckbox) {
            selectAllUsersCheckbox.addEventListener('change', function () {
                const shouldSelectAll = selectAllUsersCheckbox.checked;
                userRowCheckboxes.forEach(function (checkbox) {
                    checkbox.checked = shouldSelectAll;
                });
                syncSelectAllState();
            });
        }

        userRowCheckboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', syncSelectAllState);
        });

        syncSelectAllState();

        usersDeleteForm.addEventListener('submit', function (event) {
            if (usersDeleteForm.getAttribute('data-confirmed') === '1') {
                return;
            }

            const selectedCount = getSelectedUsersCount();
            if (selectedCount <= 0) {
                event.preventDefault();

                if (window.Swal) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'No Users Selected',
                        text: 'Select at least one user before deleting.',
                        confirmButtonText: 'OK',
                        customClass: {
                            popup: 'admin-swal-popup',
                            title: 'admin-swal-title',
                            htmlContainer: 'admin-swal-text',
                            confirmButton: 'admin-swal-confirm'
                        }
                    });
                }

                return;
            }

            event.preventDefault();

            const promptTitle = selectedCount === 1
                ? 'Delete selected user?'
                : 'Delete selected users?';
            const promptText = selectedCount === 1
                ? 'This will permanently delete the user and related logs and feedback.'
                : 'This will permanently delete the selected users and their related logs and feedback.';

            if (!window.Swal) {
                if (window.confirm(promptText)) {
                    usersDeleteForm.setAttribute('data-confirmed', '1');
                    usersDeleteForm.submit();
                }
                return;
            }

            Swal.fire({
                icon: 'warning',
                title: promptTitle,
                text: promptText,
                showCancelButton: true,
                confirmButtonText: 'Yes, Delete',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                customClass: {
                    popup: 'admin-swal-popup',
                    title: 'admin-swal-title',
                    htmlContainer: 'admin-swal-text',
                    confirmButton: 'admin-swal-confirm'
                }
            }).then(function (result) {
                if (!result.isConfirmed) {
                    return;
                }

                let actionInput = usersDeleteForm.querySelector('input[name="admin_action"]');
                if (!actionInput) {
                    actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'admin_action';
                    usersDeleteForm.appendChild(actionInput);
                }
                actionInput.value = 'delete_users_bulk';

                usersDeleteForm.setAttribute('data-confirmed', '1');
                usersDeleteForm.submit();
            });
        });
    }

    const forms = document.querySelectorAll('form');
    forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.hasAttribute('data-users-delete-form') && form.getAttribute('data-confirmed') !== '1') {
                return;
            }

            const submitButton = event.submitter || form.querySelector('button[type="submit"]');

            if (submitButton && submitButton.name) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = submitButton.name;
                hidden.value = submitButton.value || '1';
                form.appendChild(hidden);
            }

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Processing...';
            }
        });
    });
});
