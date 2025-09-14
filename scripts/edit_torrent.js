  function previewURLImage(url) {
    const img = document.getElementById('urlImagePreview');
    if (url) {
      img.src = url;
      img.style.display = 'block';
    } else {
      img.src = '#';
      img.style.display = 'none';
    }
  }

  function readFileImage(input) {
    const preview = document.getElementById('fileImagePreview');
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = function (e) {
        preview.src = e.target.result;
        preview.style.display = 'block';
      };
      reader.readAsDataURL(input.files[0]);
    } else {
      preview.src = '#';
      preview.style.display = 'none';
    }
  }
  
  
  
  
  
  function previewURLImage2(url) {
    const img = document.getElementById('urlImagePreview2');
    if (url) {
      img.src = url;
      img.style.display = 'block';
    } else {
      img.src = '#';
      img.style.display = 'none';
    }
  }

  function readFileImage2(input) {
    const preview = document.getElementById('fileImagePreview2');
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = function (e) {
        preview.src = e.target.result;
        preview.style.display = 'block';
      };
      reader.readAsDataURL(input.files[0]);
    } else {
      preview.src = '#';
      preview.style.display = 'none';
    }
  }
  
  
  
  
   $(document).ready(function () {
      $("#insert_form").on("submit", function (e) {
        const name = $.trim($("#name").val());
        const descr = $.trim($("#descr").val());
        //const imageURL = $.trim($("#t_image_url").val());
        //const imageFile = $("#t_image_file")[0].files.length;

        // Validate required fields
        if (!name || !descr) {
          alert("Please fill out all required fields.");
          e.preventDefault();
          return;
        }

        // Validate image: require at least one of URL or File
        //if (!imageURL && imageFile === 0) {
          //alert("Please provide at least one image (URL or upload).");
         // e.preventDefault();
        //}
      });
    });
  
  
  
  
  
  
  
  
  
  
  

  



  
  
  
  
  

  


