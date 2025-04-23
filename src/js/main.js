/* src/js/main.js */
document.addEventListener("DOMContentLoaded", () => {
    const currentUser = document.body.dataset.user || "Guest";
    if (!Array.isArray(window.sublets)) window.sublets = [];

    let images = [];
    let currentIndex = 0;

    const detailModal = document.getElementById("detailModal");
    const inner = detailModal.querySelector("div > div");
    const modalClose = document.getElementById("modalClose");

    const showModal = () => detailModal.classList.replace("hidden", "flex");
    const hideModal = () => detailModal.classList.replace("flex", "hidden");

    modalClose?.addEventListener("click", hideModal);
    detailModal?.addEventListener("click", e => {
        if (e.target === detailModal) hideModal();
    });

    function buildSlider() {
        // remove old
        detailModal.querySelector(".modal-slider")?.remove();

        const slider = document.createElement("div");
        slider.className = "modal-slider flex items-center justify-center mb-4";

        const prev = document.createElement("button");
        prev.className = "prev mr-2 text-2xl px-4 py-1";
        prev.textContent = "‹";
        prev.addEventListener("click", () => {
            if (currentIndex > 0) {
                currentIndex--;
                renderSlider();
            }
        });

        const img = document.createElement("img");
        img.className = "mx-2 max-h-64 object-cover rounded";
        img.alt = "Sublet image";

        const next = document.createElement("button");
        next.className = "next ml-2 text-2xl px-4 py-1";
        next.textContent = "›";
        next.addEventListener("click", () => {
            if (currentIndex < images.length - 1) {
                currentIndex++;
                renderSlider();
            }
        });

        slider.append(prev, img, next);
        inner.prepend(slider);
    }

    function renderSlider() {
        const slider = detailModal.querySelector(".modal-slider");
        if (!slider) return;
        slider.querySelector("img").src = images[currentIndex];
        slider.querySelector(".prev").style.display =
            currentIndex > 0 ? "" : "none";
        slider.querySelector(".next").style.display =
            currentIndex < images.length - 1 ? "" : "none";
    }

    window.openModal = id => {
        const s = window.sublets.find(x => x.id === id);
        if (!s) return;

        // set text fields
        document.getElementById("modalPrice").textContent = `$${s.price}`;
        document.getElementById("modalAddress").textContent = `Address: ${s.address}`;
        document.getElementById("modalSemester").textContent = `Semester: ${{ summer25: "Summer 2025", fall25: "Fall 2025", spring26: "Spring 2026" }[
            s.semester
            ] || s.semester
            }`;

        // rebuild actions
        const actions = document.getElementById("modalActions");
        actions.innerHTML = "";
        if (currentUser === "aperkel") {
            const del = document.createElement("a");
            del.href = `delete_post.php?id=${id}`;
            del.textContent = "Delete";
            del.className = "px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700";
            actions.appendChild(del);
        }
        const contact = document.createElement("a");
        contact.href = `mailto:${s.username}@uvm.edu?subject=${encodeURIComponent(
            "Interested in your sublet"
        )}`;
        contact.textContent = "Contact";
        contact.className =
            "px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700";
        actions.appendChild(contact);

        // set up slider
        images = [s.image_url];
        currentIndex = 0;
        buildSlider();
        renderSlider();

        // fetch extras
        fetch(`get_sublet_images.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (Array.isArray(data) && data.length) {
                    images = data;
                    currentIndex = 0;
                    renderSlider();
                }
            })
            .catch(console.error);

        showModal();
    };

    // wire up all grid buttons
    document.querySelectorAll(".grid-item button").forEach(btn => {
        btn.addEventListener("click", () => {
            const id = parseInt(btn.closest(".grid-item").dataset.id, 10);
            window.openModal(id);
        });
    });


    // Map page init (if applicable)
    if (document.body.classList.contains("map") && window.L) {
        const mapElem = document.getElementById("map");
        if (mapElem) {
            const leafletMap = L.map("map").setView([44.477435, -73.195323], 14);
            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                attribution: "&copy; OpenStreetMap contributors"
            }).addTo(leafletMap);

            const bounds = L.latLngBounds();
            window.sublets.forEach(sublet => {
                const marker = L.marker([sublet.lat, sublet.lon]).addTo(leafletMap);
                marker.bindPopup(`
            <img src="${sublet.image_url}" class="w-48 h-32 object-cover rounded mb-2 cursor-pointer"
                 onclick="openModal(${sublet.id})">
            <div><strong>$${sublet.price}</strong><br>${sublet.address}</div>
          `);
                bounds.extend(marker.getLatLng());
            });
            if (bounds.isValid()) {
                leafletMap.fitBounds(bounds, { padding: [50, 50] });
            }
            window.addEventListener("resize", () => leafletMap.invalidateSize());
        }
    }
});
