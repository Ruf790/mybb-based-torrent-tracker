// Custom Integer Parsing Function
        function intval(B,C) {
            var A;
            if (typeof (B) == "string") {
                A = parseInt(B * 1);
                if (isNaN(A) || !isFinite(A)) {
                    return 0;
                } else {
                    return A.toString(C || 10);
                }
            } else {
                if (typeof (B) == "number" && isFinite(B)) {
                    return Math.floor(B);
                } else {
                    return 0;
                }
            }
        }

        // IMDb Update Function with Bootstrap Integration
        function TS_IMDB(A) {
            var B = "tid=" + intval(A);
            $("#imdbupdatebutton").text('Please Wait...').prop('disabled', true); // Update button text and disable it
            $.ajax({
                url: baseurl + "/ts_ajax5.php",
                type: "POST",
                data: B,
                contentType: "application/x-www-form-urlencoded",
                encoding: charset,
                beforeSend: function() {
                    $("#imdbupdatebutton").text('Please Wait...').prop('disabled', true);
                },
                success: function(response) {
                    if (response.match(/<error>(.*)<\/error>/)) {
                        var message = response.match(/<error>(.*)<\/error>/);
                        if (!message[1]) {
                            message[1] = l_ajaxerror;
                        }
                        alert(l_updateerror + message[1]);
                        $("#imdbupdatebutton").text('Refresh').prop('disabled', false); // Reset button text
                    } else {
                        $("#imdbdetails").html(response);
                        $("#imdbupdatebutton").text('Updated').prop('disabled', false); // Update button text
                        // Highlight the updated content
                        $("#imdbdetails").parent().addClass('bg-warning').removeClass('bg-light').fadeOut().fadeIn();
                    }
                },
                error: function() {
                    alert(l_ajaxerror);
                    $("#imdbupdatebutton").text('Refresh').prop('disabled', false); // Reset button text
                }
            });
        }