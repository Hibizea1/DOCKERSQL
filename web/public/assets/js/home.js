const searchInput = document.getElementById("searchInput");
const searchButton = document.getElementById("searchButton");
const homeHero = document.querySelector(".home-hero");
const heroTitleBlock = document.getElementById("heroTitleBlock");
const scrollButtons = document.querySelectorAll(".scroll-btn");
const categoryScrolls = document.querySelectorAll(".category-scroll");
const menuToggleButton = document.getElementById("menuToggleButton");
const menuDropdownContent = document.querySelector(".menu-dropdown .dropdown-content");
const goHomeButton = document.getElementById("goHomeButton");
const goProfileButton = document.getElementById("goProfileButton");
const goSettingsButton = document.getElementById("goSettingsButton");
const goLoginButton = document.getElementById("goLoginButton");
const goWikiButton = document.getElementById("goWikiButton");

function openProfilePage(username) {
    const cleanUsername = username.trim();

    if (cleanUsername.length > 0) {
        window.location.href = `profil.html?user=${encodeURIComponent(cleanUsername)}`;
        return;
    }

    window.location.href = "profil.html";
}

if (searchButton && searchInput) {
    searchButton.addEventListener("click", () => {
        openProfilePage(searchInput.value);
    });

    searchInput.addEventListener("keydown", (event) => {
        if (event.key === "Enter") {
            event.preventDefault();
            openProfilePage(searchInput.value);
        }
    });
}

function setupTopMenuDropdown() {
    if (!menuToggleButton || !menuDropdownContent) {
        return;
    }

    const setMenuExpanded = (isOpen) => {
        menuDropdownContent.classList.toggle("show", isOpen);
        menuToggleButton.setAttribute("aria-expanded", String(isOpen));
    };

    menuToggleButton.addEventListener("click", (event) => {
        event.stopPropagation();
        const next = !menuDropdownContent.classList.contains("show");
        setMenuExpanded(next);
    });

    document.addEventListener("click", (event) => {
        if (!menuDropdownContent.contains(event.target) && event.target !== menuToggleButton) {
            setMenuExpanded(false);
        }
    });

    if (goLoginButton) {
        goLoginButton.addEventListener("click", () => {
            window.location.href = "login.html";
        });
    }

    if (goHomeButton) {
        goHomeButton.addEventListener("click", () => {
            window.location.href = "home.html";
        });
    }

    if (goProfileButton) {
        goProfileButton.addEventListener("click", () => {
            window.location.href = "profil.html";
        });
    }

    if (goSettingsButton) {
        goSettingsButton.addEventListener("click", () => {
            window.location.href = "settings.html";
        });
    }

    if (goWikiButton) {
        goWikiButton.addEventListener("click", () => {
            window.location.href = "wiki.html";
        });
    }
}

function setupHeroTitleOnScroll() {
    if (!heroTitleBlock) {
        return;
    }

    const updateTitleVisibility = () => {
        const hideThreshold = Math.max(80, Math.floor(window.innerHeight * 0.12));
        if (window.scrollY > hideThreshold) {
            heroTitleBlock.classList.add("collapsed");
        } else {
            heroTitleBlock.classList.remove("collapsed");
        }
    };

    updateTitleVisibility();
    window.addEventListener("scroll", updateTitleVisibility, { passive: true });
}

function setHeroBackground() {
    if (!homeHero) {
        return;
    }

    const candidates = [
        "../assets/img/Items/obsidianBow.png",
        "../assets/img/items/obsidianBow.png",
        "../assets/img/slots/weapon.png"
    ];

    const tryLoad = (index) => {
        if (index >= candidates.length) {
            return;
        }

        const img = new Image();
        img.onload = () => {
            homeHero.style.backgroundImage =
                `linear-gradient(120deg, rgba(8, 10, 13, 0.9), rgba(36, 58, 78, 0.75)), url("${candidates[index]}")`;
        };
        img.onerror = () => {
            tryLoad(index + 1);
        };
        img.src = candidates[index];
    };

    tryLoad(0);
}

function setupHorizontalCategoryScroll() {
    scrollButtons.forEach((button) => {
        button.addEventListener("click", () => {
            const targetId = button.dataset.target;
            const direction = button.dataset.dir;
            const target = document.getElementById(targetId);

            if (!target) {
                return;
            }

            const offset = direction === "left" ? -360 : 360;
            target.scrollBy({ left: offset, behavior: "smooth" });
        });
    });

    categoryScrolls.forEach((track) => {
        track.querySelectorAll("img").forEach((img) => {
            img.setAttribute("draggable", "false");
        });

        const step = () => Math.max(220, Math.floor(track.clientWidth * 0.75));
        let isDragging = false;
        let pointerId = null;
        let dragDelta = 0;
        let rafId = null;
        let startX = 0;
        let startScrollLeft = 0;

        const autoScroll = () => {
            const amount = step();
            const nearEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 8;

            if (nearEnd) {
                track.scrollTo({ left: 0, behavior: "smooth" });
                return;
            }

            track.scrollBy({ left: amount, behavior: "smooth" });
        };

        let autoIntervalId = window.setInterval(autoScroll, 10000);

        const restartAuto = () => {
            window.clearInterval(autoIntervalId);
            autoIntervalId = window.setInterval(autoScroll, 10000);
        };

        track.addEventListener("mouseenter", () => {
            window.clearInterval(autoIntervalId);
        });

        track.addEventListener("mouseleave", () => {
            if (isDragging) {
                isDragging = false;
                pointerId = null;
                track.classList.remove("dragging");
            }
            restartAuto();
        });

        const applyDrag = () => {
            if (!isDragging) {
                rafId = null;
                return;
            }

            track.scrollLeft = startScrollLeft - dragDelta;
            rafId = window.requestAnimationFrame(applyDrag);
        };

        track.addEventListener("pointerdown", (event) => {
            if (event.button !== 0) {
                return;
            }

            isDragging = true;
            pointerId = event.pointerId;
            startX = event.clientX;
            startScrollLeft = track.scrollLeft;
            dragDelta = 0;
            track.classList.add("dragging");
            track.setPointerCapture(pointerId);
            window.clearInterval(autoIntervalId);

            if (rafId === null) {
                rafId = window.requestAnimationFrame(applyDrag);
            }
        });

        track.addEventListener("pointermove", (event) => {
            if (!isDragging || event.pointerId !== pointerId) {
                return;
            }

            dragDelta = event.clientX - startX;
            event.preventDefault();
        });

        const stopDragging = (event) => {
            if (!isDragging || event.pointerId !== pointerId) {
                return;
            }

            isDragging = false;
            pointerId = null;
            track.classList.remove("dragging");
            if (rafId !== null) {
                window.cancelAnimationFrame(rafId);
                rafId = null;
            }
            restartAuto();
        };

        track.addEventListener("pointerup", stopDragging);
        track.addEventListener("pointercancel", stopDragging);

        track.addEventListener(
            "wheel",
            (event) => {
                if (Math.abs(event.deltaY) <= Math.abs(event.deltaX)) {
                    return;
                }

                event.preventDefault();
                track.scrollLeft += event.deltaY;
                restartAuto();
            },
            { passive: false }
        );
    });
}

window.addEventListener("load", () => {
    setupTopMenuDropdown();
    setHeroBackground();
    setupHeroTitleOnScroll();
    setupHorizontalCategoryScroll();
});