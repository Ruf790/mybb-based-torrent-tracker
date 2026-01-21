// Custom Integer Parsing Function
function parseInteger(value, radix) {
    if (typeof value === "string") {
        const parsedNumber = parseInt(value * 1);
        if (isNaN(parsedNumber) || !isFinite(parsedNumber)) {
            return 0;
        } else {
            return parsedNumber.toString(radix || 10);
        }
    } else {
        if (typeof value === "number" && isFinite(value)) {
            return Math.floor(value);
        } else {
            return 0;
        }
    }
}


// IMDb Update Function using native JavaScript
function TS_IMDB(torrentId) {
    const updateButton = document.getElementById('imdbupdatebutton');
    const imdbDetails = document.getElementById('imdbdetails');
    
    if (!updateButton || !imdbDetails) {
        console.error('Required elements not found');
        return;
    }
    
    const postData = "tid=" + parseInteger(torrentId);
    
    // Update button state
    updateButton.textContent = 'Please Wait...';
    updateButton.disabled = true;
    
    fetch(baseurl + "/ajax_imdb.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
        },
        body: postData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.text();
    })
    .then(response => {
        if (response.match(/<error>(.*)<\/error>/)) {
            const errorMatch = response.match(/<error>(.*)<\/error>/);
            const errorMessage = errorMatch[1] || 'An error occurred';
            
            alert('Update error: ' + errorMessage);
            updateButton.textContent = 'Refresh';
            updateButton.disabled = false;
        } else {
            imdbDetails.innerHTML = response;
            updateButton.textContent = 'Updated';
            updateButton.disabled = false;
            
            // Visual feedback
            const parentContainer = imdbDetails.parentElement;
            parentContainer.classList.add('bg-warning');
            parentContainer.classList.remove('bg-light');
            
            // Simple fade effect
            parentContainer.style.opacity = '0';
            setTimeout(() => {
                parentContainer.style.opacity = '1';
            }, 200);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('AJAX error occurred');
        updateButton.textContent = 'Refresh';
        updateButton.disabled = false;
    });
}