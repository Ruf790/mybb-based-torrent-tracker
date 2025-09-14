var ajax = {
    gets: function(url) {
        return fetch(url)
            .then(response => response.text()) // Expecting HTML content as a response
            .then(data => {
                return data; // Return the HTML content (you can return HTML as a string)
            })
            .catch(error => {
                console.error('Error fetching HTML:', error);
                return ''; // Return an empty string in case of an error
            });
    }
};

function bookmark(torrentid, counter) {
    // Simulate sending request and updating the bookmark status with HTML response
    var result = ajax.gets('bookmark.php?torrentid=' + torrentid);
    result.then(html => {
        bmicon(html, counter);  // Use HTML content returned from server
    });
}



function bmicon(html, counter) {
        const iconElement = document.getElementById("bookmark" + counter);
        
        // Insert the HTML (icon) returned by the server into the element
        iconElement.innerHTML = html;
}