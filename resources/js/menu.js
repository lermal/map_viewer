document.addEventListener("DOMContentLoaded", function () {
    const renderToggle =
        document.querySelector(".render-toggle") ||
        document.querySelector(".shuttle-toggle");
    const filterToggle = document.querySelector(".filter-toggle");
    const dropdownContent = document.querySelector(".dropdown-content");
    const filterDropdown = document.querySelector(".filter-dropdown");
    const renderItems = document.querySelectorAll(".render-item");
    const filterCheckboxes = document.querySelectorAll(".render-filter");
    const noResultsMessage = document.getElementById("no-results-message");
    const pageSlug = window.pageSlug || "";

    if (renderToggle && dropdownContent) {
        renderToggle.addEventListener("click", function () {
            dropdownContent.classList.toggle("show");
        });
    }

    if (filterToggle && filterDropdown) {
        filterToggle.addEventListener("click", function () {
            filterDropdown.classList.toggle("show");
        });
    }

    function applyFilters() {
        const activeFilters = {};
        let hasActiveFilters = false;

        filterCheckboxes.forEach((checkbox) => {
            if (checkbox.checked) {
                const filterType = checkbox.getAttribute("data-filter-type");
                const filterValue = checkbox.value;

                if (!activeFilters[filterType]) {
                    activeFilters[filterType] = [];
                }

                activeFilters[filterType].push(filterValue);
                hasActiveFilters = true;
            }
        });

        let visibleCount = 0;
        const visibleItems = [];

        renderItems.forEach((item) => {
            let shouldShow = true;

            if (hasActiveFilters) {
                for (const [filterType, filterValues] of Object.entries(
                    activeFilters
                )) {
                    const itemValue = item.getAttribute(`data-${filterType}`);

                    if (!itemValue) {
                        shouldShow = false;
                        break;
                    }

                    const itemValues = itemValue
                        .split(", ")
                        .map((v) => v.trim());
                    const hasMatch = filterValues.some((fv) =>
                        itemValues.includes(fv)
                    );

                    if (!hasMatch) {
                        shouldShow = false;
                        break;
                    }
                }
            }

            if (shouldShow) {
                item.style.display = "";
                visibleCount++;
                visibleItems.push(item);
            } else {
                item.style.display = "none";
            }
        });

        const categories = document.querySelectorAll(".dropdown-category");
        categories.forEach((category) => {
            const visibleCategoryItems = category.querySelectorAll(
                '.render-item:not([style*="display: none"])'
            );
            if (visibleCategoryItems.length === 0) {
                category.style.display = "none";
            } else {
                category.style.display = "";
            }
        });

        if (visibleCount === 0) {
            noResultsMessage.style.display = "block";
        } else {
            noResultsMessage.style.display = "none";
        }

        updateFilterAvailability(visibleItems, activeFilters);
    }

    function updateFilterAvailability(visibleItems, activeFilters) {
        const filterGroups = {};

        filterCheckboxes.forEach((checkbox) => {
            const filterType = checkbox.getAttribute("data-filter-type");
            if (!filterGroups[filterType]) {
                filterGroups[filterType] = [];
            }
            filterGroups[filterType].push(checkbox);
        });

        Object.keys(filterGroups).forEach((filterType) => {
            filterGroups[filterType].forEach((checkbox) => {
                const filterValue = checkbox.value;
                const isChecked = checkbox.checked;

                if (isChecked) {
                    checkbox.disabled = false;
                    checkbox.closest(".checkbox-item").style.opacity = "1";
                    return;
                }

                const hasMatchingItem = visibleItems.some((item) => {
                    const itemValue = item.getAttribute(`data-${filterType}`);
                    if (!itemValue) {
                        return false;
                    }
                    const itemValues = itemValue
                        .split(", ")
                        .map((v) => v.trim());
                    return itemValues.includes(filterValue);
                });

                if (hasMatchingItem) {
                    checkbox.disabled = false;
                    checkbox.closest(".checkbox-item").style.opacity = "1";
                } else {
                    checkbox.disabled = true;
                    checkbox.closest(".checkbox-item").style.opacity = "0.5";
                }
            });
        });
    }

    filterCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener("change", applyFilters);
    });

    applyFilters();

    renderItems.forEach((item) => {
        item.addEventListener("click", function () {
            const itemId = this.getAttribute("data-id");
            if (itemId && pageSlug) {
                window.location.href = `/render/${pageSlug}/${itemId}`;
            }
        });
    });

    document.addEventListener("click", function (event) {
        if (
            renderToggle &&
            !renderToggle.contains(event.target) &&
            !dropdownContent.contains(event.target)
        ) {
            dropdownContent.classList.remove("show");
        }

        if (
            filterToggle &&
            !filterToggle.contains(event.target) &&
            !filterDropdown.contains(event.target)
        ) {
            filterDropdown.classList.remove("show");
        }
    });
});
