// Simple pill selection logic
// When a pill is clicked, update its hidden input and toggle active state
document.querySelectorAll(".pill-btn").forEach(btn => {
    btn.addEventListener("click", function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const groupName = this.dataset.name;
        const groupValue = this.dataset.value || ''; // Can be empty for "Any"
        
        console.log('🔵 PILL CLICKED:', groupName, '=', groupValue);
        
        // Remove active from all pills in this group
        document.querySelectorAll(`.pill-btn[data-name="${groupName}"]`)
            .forEach(b => b.classList.remove("active"));
        
        // Add active to clicked pill
        this.classList.add("active");
        console.log('🟢 Active class added to clicked pill');
        
        // Update the hidden input in the form
        const form = this.closest("form");
        if (form) {
            const hiddenInput = form.querySelector(`input[type="hidden"][name="${groupName}"]`);
            if (hiddenInput) {
                hiddenInput.value = groupValue;
                console.log('📝 Hidden input updated:', groupName, '=', hiddenInput.value);
            } else {
                console.log('⚠️  WARNING: Hidden input not found for:', groupName);
            }
        } else {
            console.log('⚠️  WARNING: Form not found for pill button');
        }
    });
});

// Before form submission, ensure hidden inputs sync with active pills
document.addEventListener("submit", function(e) {
    console.log('📤 FORM SUBMIT EVENT:', e.target.tagName);
    
    if (e.target && e.target.tagName === "FORM") {
        console.log('🔍 Syncing active pills to hidden inputs before submission...');
        
        // Sync all active pills to their hidden inputs
        document.querySelectorAll(".pill-btn.active").forEach(pill => {
            const fieldName = pill.dataset.name;
            const fieldValue = pill.dataset.value || '';
            const form = e.target;
            const hiddenInput = form.querySelector(`input[type="hidden"][name="${fieldName}"]`);
            if (hiddenInput) {
                hiddenInput.value = fieldValue;
                console.log('📝 Synced:', fieldName, '=', fieldValue);
            }
        });
        
        // Log all form data being sent
        console.log('📋 Form data about to be submitted:');
        const formData = new FormData(e.target);
        for (let [key, value] of formData.entries()) {
            console.log('   ', key, '=', value);
        }
    }
}, true); // Use capture phase to ensure this runs before other handlers

// Sidebar toggle
document.getElementById("sidebarToggle")?.addEventListener("click", function () {
    const sidebar = document.getElementById("sidebar");
    const mainContent = document.querySelector(".main-content");
    const isMobile = window.innerWidth <= 991;

    if (isMobile) {
        sidebar.classList.toggle("open");
    } else {
        sidebar.classList.toggle("collapsed");
        if (mainContent) {
            mainContent.style.marginLeft = sidebar.classList.contains("collapsed")
                ? "0"
                : "280px";
        }
    }
});

// Ensure correct layout on resize
window.addEventListener("resize", function () {
    const sidebar = document.getElementById("sidebar");
    const mainContent = document.querySelector(".main-content");
    if (!sidebar || !mainContent) return;

    if (window.innerWidth <= 991) {
        mainContent.style.marginLeft = "0";
    } else if (!sidebar.classList.contains("collapsed")) {
        mainContent.style.marginLeft = "280px";
    }
});

document.addEventListener("DOMContentLoaded", function () {
    const closePublicNavbarMenu = () => {
        const navbarCollapse = document.getElementById('navbarContent');
        if (!navbarCollapse || !navbarCollapse.classList.contains('show')) {
            return;
        }

        const toggler = document.querySelector('.main-navbar .navbar-toggler');

        if (window.bootstrap && window.bootstrap.Collapse) {
            const collapse = window.bootstrap.Collapse.getOrCreateInstance(navbarCollapse);
            collapse.hide();
        } else {
            navbarCollapse.classList.remove('show');
        }

        if (toggler) {
            toggler.setAttribute('aria-expanded', 'false');
        }
    };

    const navbarCollapse = document.getElementById('navbarContent');
    if (navbarCollapse) {
        navbarCollapse.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 991) {
                    closePublicNavbarMenu();
                }
            });
        });
    }

    const ensureSwal = () => {
        if (window.Swal) {
            return Promise.resolve(window.Swal);
        }

        return new Promise((resolve, reject) => {
            const existing = document.querySelector('script[data-swal-lib="1"]');
            if (existing) {
                existing.addEventListener('load', () => resolve(window.Swal));
                existing.addEventListener('error', reject);
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
            script.setAttribute('data-swal-lib', '1');
            script.onload = () => resolve(window.Swal);
            script.onerror = reject;
            document.head.appendChild(script);
        });
    };

    document.querySelectorAll('[data-confirm-logout="public"]').forEach(link => {
        link.addEventListener('click', async function (event) {
            event.preventDefault();

            const logoutUrl = this.getAttribute('href');
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
                        popup: 'dc-swal-popup',
                        title: 'dc-swal-title',
                        htmlContainer: 'dc-swal-text',
                        confirmButton: 'dc-swal-confirm',
                        cancelButton: 'dc-swal-cancel'
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

    const logRecommendationRelevance = (card) => {
        if (!card || card.getAttribute('data-recommended') !== '1') {
            return;
        }

        const recipeId = Number(card.getAttribute('data-id') || 0);
        if (!recipeId) {
            return;
        }

        const logId = Number(card.getAttribute('data-log-id') || 0);
        const params = new URLSearchParams();
        params.append('log_recommendation_relevance', '1');
        params.append('recipe_id', String(recipeId));
        if (logId > 0) {
            params.append('log_id', String(logId));
        }
        params.append('interaction_type', 'click');

        try {
            if (navigator.sendBeacon) {
                const blob = new Blob([params.toString()], {
                    type: 'application/x-www-form-urlencoded;charset=UTF-8'
                });
                navigator.sendBeacon('../core/handleForms.php', blob);
                return;
            }

            fetch('../core/handleForms.php', {
                method: 'POST',
                body: params,
                keepalive: true,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).catch(() => {});
        } catch (error) {
            console.error('Failed to log recommendation relevance:', error);
        }
    };

    const ensureToastContainer = () => {
        let container = document.querySelector('.bookmark-toast-container');
        if (container) {
            return container;
        }

        container = document.createElement('div');
        container.className = 'bookmark-toast-container';
        document.body.appendChild(container);
        return container;
    };

    const showBookmarkToast = (message, type = 'success') => {
        const container = ensureToastContainer();
        const toast = document.createElement('div');
        toast.className = `bookmark-toast ${type === 'error' ? 'is-error' : 'is-success'}`;
        toast.textContent = message;
        container.appendChild(toast);

        window.requestAnimationFrame(() => {
            toast.classList.add('is-visible');
        });

        window.setTimeout(() => {
            toast.classList.remove('is-visible');
            window.setTimeout(() => {
                toast.remove();
            }, 220);
        }, 1700);
    };

    const setBookmarkVisualState = (button, saved) => {
        if (!button) return;

        const icon = button.querySelector("i");
        button.classList.toggle("is-saved", Boolean(saved));

        if (icon) {
            icon.classList.toggle("bi-bookmark-heart-fill", Boolean(saved));
            icon.classList.toggle("bi-bookmark-heart", !saved);
        }
    };

    const toggleBookmark = async (button) => {
        const recipeId = Number(button.getAttribute("data-recipe-id") || 0);
        if (!recipeId) {
            return;
        }

        const card = button.closest('.recipe-card[data-id]');
        const logIdFromCard = card ? Number(card.getAttribute('data-log-id') || 0) : 0;

        const formData = new FormData();
        formData.append("toggle_bookmark", "1");
        formData.append("recipe_id", String(recipeId));
        if (logIdFromCard > 0) {
            formData.append("log_id", String(logIdFromCard));
        }

        button.disabled = true;

        try {
            const response = await fetch("../core/handleForms.php", {
                method: "POST",
                body: formData,
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                }
            });

            const payload = await response.json();
            if (!response.ok || !payload.ok) {
                throw new Error(payload.message || "Failed to update favorite state.");
            }

            setBookmarkVisualState(button, Boolean(payload.saved));
            showBookmarkToast(payload.message || (payload.saved ? 'Recipe saved.' : 'Recipe removed.'));

            if (!payload.saved && window.location.pathname.toLowerCase().endsWith('/bookmarks.php')) {
                const cardColumn = button.closest('.col-xl-3, .col-lg-4, .col-md-6, .col-xl-4, .col-lg-6');
                if (cardColumn) {
                    cardColumn.remove();
                }

                const remainingCards = document.querySelectorAll('.recipe-card.listing-card').length;
                if (remainingCards === 0) {
                    const row = document.querySelector('.main-content .row.g-4');
                    if (row) {
                        row.outerHTML = '<div class="alert alert-warning" role="alert"><i class="bi bi-bookmark-heart me-2"></i>You have no saved recipes yet.</div>';
                    }
                }
            }
        } catch (error) {
            console.error("Bookmark toggle failed:", error);
            showBookmarkToast('Could not update favorites right now.', 'error');
        } finally {
            button.disabled = false;
        }
    };

    document.querySelectorAll(".recipe-card[data-id]").forEach(card => {
        card.addEventListener("click", function () {
            const id = this.dataset.id;
            if (id) {
                const isRecommended = this.getAttribute('data-recommended') === '1';
                if (isRecommended) {
                    logRecommendationRelevance(this);
                }

                const source = isRecommended ? 'dashboard_recommendation' : 'dashboard_random';
                window.location.href = `recipe.php?id=${id}&source=${encodeURIComponent(source)}`;
            }
        });

        card.addEventListener("keydown", function (e) {
            if (e.key === "Enter" || e.key === " ") {
                e.preventDefault();
                const id = this.dataset.id;
                if (id) {
                    const isRecommended = this.getAttribute('data-recommended') === '1';
                    if (isRecommended) {
                        logRecommendationRelevance(this);
                    }

                    const source = isRecommended ? 'dashboard_recommendation' : 'dashboard_random';
                    window.location.href = `recipe.php?id=${id}&source=${encodeURIComponent(source)}`;
                }
            }
        });
    });

    document.querySelectorAll(".save-icon").forEach(btn => {
        btn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            toggleBookmark(this);
        });
    });

    document.querySelectorAll(".recipe-save-btn").forEach(btn => {
        btn.addEventListener("click", function (e) {
            e.preventDefault();
            toggleBookmark(this);
        });
    });

});

console.log('✅ layout.js loaded and ready');
