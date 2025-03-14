function debounce(func, delay) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), delay);
    };
}

function verifyAddress() {
    const addressInput = document.getElementById("address");
    geocoder.geocode({ address: addressInput.value }, function (results, status) {
        if (status === "OK" && results[0]) {
            const location = results[0].geometry.location;
            document.getElementById("lat").value = location.lat();
            document.getElementById("lon").value = location.lng();
            // Update the marker's position and recenter the map
            marker.setPosition(location);
            map.setCenter(location);
        }
    });
}

document.addEventListener("DOMContentLoaded", function () {
    // Update upload button text
    document.getElementById('image_url').addEventListener('change', function () {
        let fileName = this.files[0]?.name || 'UPLOAD';
        document.querySelector('label[for="image_url"]').textContent = fileName;
    });

    const debouncedVerifyAddress = debounce(verifyAddress, 500);
    document.getElementById("address").addEventListener("input", debouncedVerifyAddress);
});

function initMap() {
    map = new google.maps.Map(document.getElementById("map"), {
        zoom: 15,
        center: { lat: 44.477435, lng: -73.195323 },
        mapId: '3bd5c9ae8c849605' // Replace YOUR_MAP_ID with your actual Map ID
    });
    geocoder = new google.maps.Geocoder();

    // Create an Advanced Marker Element (requires the marker library)
    marker = new google.maps.marker.AdvancedMarkerElement({
        map: map,
        position: { lat: 44.477435, lng: -73.195323 },
        title: "Sublet Location"
    });

    let autocomplete = new google.maps.places.Autocomplete(document.getElementById('address'), {
        types: ['geocode']
    });
    autocomplete.addListener('place_changed', function () {
        let place = autocomplete.getPlace();
        if (place.geometry) {
            document.getElementById('lat').value = place.geometry.location.lat();
            document.getElementById('lon').value = place.geometry.location.lng();
            // Update the marker position using AdvancedMarkerElement:
            marker.position = place.geometry.location;
            map.setCenter(place.geometry.location);
        }
        verifyAddress();
    });
}



document.addEventListener('DOMContentLoaded', function () {
    // Mapping function for semester codes
    function getFriendlySemester(code) {
        const mapping = {
            "summer25": "Summer 2025",
            "fall25": "Fall 2025",
            "spring26": "Spring 2026"
        };
        return mapping[code] || code;
    }

    var modal = document.getElementById('subletModal');

    document.querySelectorAll('.grid-item').forEach(function (item) {
        item.addEventListener('click', function () {
            var posterUsername = item.getAttribute('data-username');
            var price = item.getAttribute('data-price');
            var address = item.getAttribute('data-address');
            var semesterCode = item.getAttribute('data-semester');
            var friendlySemester = getFriendlySemester(semesterCode);
            var desc = item.getAttribute('data-desc');

            document.getElementById('modalImage').src = item.querySelector('img').src;
            document.getElementById('modalPrice').textContent = "Price: $" + price;
            document.getElementById('modalAddress').textContent = "Address: " + address;
            document.getElementById('modalSemester').textContent = "Semester: " + friendlySemester;
            document.getElementById('modalUsername').textContent = "Posted by: " + posterUsername;
            document.getElementById('modalDesc').innerHTML = "<br>Description: <br>" + desc;

            // Use data attribute from body for current user
            var currentUser = document.body.getAttribute('data-user') || "Guest";
            var toEmail = posterUsername + "@uvm.edu";
            var subject = "Interested in Your " + friendlySemester + " Sublet Posting";
            var body = "Hello!\n\nI’m interested in your sublet posting for " + friendlySemester + " at " + address +
                ". Could you send me more details when you have a moment?\n\nThanks,\n" + currentUser;
            var mailtoLink = "mailto:" + encodeURIComponent(toEmail) +
                "?subject=" + encodeURIComponent(subject) +
                "&body=" + encodeURIComponent(body);
            document.getElementById('modalContact').setAttribute('href', mailtoLink);

            modal.style.display = "block";
        });
    });

    // Modal close events
    document.querySelector('.close').addEventListener('click', function () {
        modal.style.display = "none";
    });
    window.addEventListener('click', function (event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    });

    // Grid resizing function
    function resizeGridItem(item) {
        const grid = document.querySelector('.grid-container');
        const rowHeight = parseInt(window.getComputedStyle(grid).getPropertyValue('grid-auto-rows'));
        const rowGap = parseInt(window.getComputedStyle(grid).getPropertyValue('grid-gap'));
        const img = item.querySelector('img');
        const height = img.getBoundingClientRect().height;
        const rowSpan = Math.ceil((height + rowGap) / (rowHeight + rowGap)) - 1;
        item.style.gridRowEnd = "span " + rowSpan;
    }

    const gridImages = document.querySelectorAll('.grid-item img');
    gridImages.forEach(function (img) {
        if (img.complete) {
            resizeGridItem(img.parentElement);
        } else {
            img.addEventListener('load', function () {
                resizeGridItem(img.parentElement);
            });
        }
    });

    window.addEventListener('resize', function () {
        document.querySelectorAll('.grid-item').forEach(function (item) {
            resizeGridItem(item);
        });
    });
});