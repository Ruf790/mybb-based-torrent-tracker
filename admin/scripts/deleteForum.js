$(document).ready(function(){  
	$('.delete_employee').click(function(e){   
	   e.preventDefault();   
	   var empid = $(this).attr('data-emp-id');
	   var parent = $(this).parent("td").parent("tr");   
	   bootbox.dialog({
			message: "Are you sure you want to Delete ?",
			title: "<i class='fa-solid fa-trash' style='color: #0d0d0d;'></i> Delete !",
			buttons: {
				success: {
					  label: "No",
					  className: "btn-success",
					  callback: function() {
					  $('.bootbox').modal('hide');
				  }
				},
				danger: {
				  label: "Delete!",
				  className: "btn-danger",
				  callback: function() {       
				   $.ajax({        
						type: 'POST',
						//url: 'deleteRecords.php',
						
						//url : 'management.php?action=delete&amp;fid='+empid,
						
						//url: 'management.php?action=delete&amp;fid' + +empid + '&my_post_key=' + my_post_key,
						
						url: 'index.php?act=management&action=delete&fid=' +empid + '&my_post_key=' + my_post_key,
						
						
						data: 'fid='+empid        
				   })
				   .done(function(response){        
						bootbox.alert(response);
						parent.fadeOut('slow');        
				   })
				   .fail(function(){        
						bootbox.alert('Error....');               
				   })              
				  }
				}
			}
	   });   
	});  
 });