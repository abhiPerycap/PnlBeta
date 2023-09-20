<?php
	$users = \App\Models\User::where('authorised', true)->get();
	$accounts = \App\Models\TradeAccount::where('authorised', true)->get();
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>{{config('app.name')}} - Account Mapping</title>
	<script src="https://code.jquery.com/jquery-3.6.0.js" integrity="sha256-H+K7U5CnXl1h5ywQfKtSj8PCmoN9aaq30gDh27Xc0jk=" crossorigin="anonymous"></script>
	<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
	<!-- CSS only -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-0evHe/X+R7YkIZDRvuzKMRqM+OrBnVFBL6DOitfPri4tjfHxaWutUpFmBp4vmVor" crossorigin="anonymous">
<!-- JavaScript Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0-beta1/dist/js/bootstrap.bundle.min.js" integrity="sha384-pprn3073KE6tl6bjs2QrFaJGz5/SUsLqktiwsUTF55Jfv3qYSDhgCecCxMW52nD2" crossorigin="anonymous"></script>


<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.12.1/css/jquery.dataTables.css">
  
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.js"></script>

</head>
<body style="font-size:10px;" onBlur="window.focus()">
	<div style="padding: 16px;">
		<div style="border: 2px solid blue;
width: fit-content;padding:5px;">

			<label for="startdate">Effective From:</label>
			<input type="date" id="startdate" name="startdate" value="{{Carbon\Carbon::today()->toDateString()}}">

			<label for="account">Account:</label>
			<select id="account" class="select2" style="width:86px;">
			 	@if(isset($accounts))
		    		@foreach($accounts as $account)
		    		<option value="{{$account['accountid']}}">{{$account['accountid']}}</option>
		    		@endforeach
	    		@endif
			</select>

			<label for="type">Type:</label>
			<select name="type" id="type" class="select2">
				<option value=''>-Select-</option>
				<option>Sub</option>
				<option>Master</option>
				<option value="disable_account">Disable Account</option>
				<option value="disable_user">Disable User</option>
			</select>

			<label for="users">Users:</label>
			<select id="users" name="users" class="select2" style="width:200px;" multiple>
			 	@if(isset($users))
		    		@foreach($users as $user)
		    		<option value="{{$user['_id']}}">{{$user['memberId']}}</option>
		    		@endforeach
	    		@endif
			</select>

			<button id="addRow" style="color: white;background: blue;">+ Add</button>

		</div>
		<div style="width:fit-content;border: 2px solid blue;padding: 3px;">
			<button class="backButton" style="color: white;background: grey;">Back</button>
			<button class="executeNoIssue" style="color: white;background: green;">Execute (OK)</button>
			<button class="refreshTable" style="color: white;background: green;">Refresh Table</button>
			<button class="executeIssue" style="color: black;background: yellow;">Execute (Issue)</button>
			<button class="executeSelected" style="color: white;background: blue;">Execute Selected</button>
			<button class="execAll" style="color: white;background: blue;">Execute All</button>
			<button class="removeSelected" style="color: white;background: red;">Remove Selected</button>
			<button class="removeAllBtn" style="color: white;background: red;">Remove All</button>
		</div>
		<div style="width:603px;border: 2px solid blue;">
			<table id="mappingTable" class="display">
			    <thead>
			        <tr>
			        	<th><input type="checkbox" name="select_all" value="1" id="example-select-all"></th>
			            <th>Effective From</th>
			            <th>Account</th>
			            <th>User</th>
			            <th>UserID</th>
			            <th>Mapping Type</th>
			            <th>Status</th>
			            <th>Status1</th>
			        </tr>
			    </thead>
			    <tbody>
			        
			    </tbody>
			</table> 

		</div>
	</div>
	<script type="text/javascript">
		var table, doneRefreshing;
		$(document).ready(function() {

		    $('.select2').select2({
              tags: false,
              theme: 'classic',
              placeholder: 'Select an option',
              // dropdownParent: $('.form-horizontal')
            });
			$('#users').select2({
				tags: false,
				theme: 'classic',
				placeholder: 'Select an option',
				// maximumSelectionLength: 1
				// dropdownParent: $('.form-horizontal')
			});
			$("#users").prop("disabled", true);

		    $('#account').select2();
		    // $('#users').select2();
		    $('#type').select2();


			doneRefreshing = true;

		    table = $('#mappingTable').DataTable({
		      'columnDefs': [
	    	  		{
					'targets': 0,
					'searchable': false,
					'orderable': false,
					'className': 'dt-body-center',
					'render': function (data, type, full, meta){
						return '<input type="checkbox" name="id[]" value="' + $('<div/>').text(data).html() + '">';
					}
			    },
				{
					"targets": [4,7],
					"visible": false,
					"searchable": false
				}
			  ],
		      'order': [[1, 'asc']]
		    });

		    enableActionPanel(table);
			$('.refreshTable').attr('disabled' , true);

			// Handle click on "Select all" control
			$('#example-select-all').on('click', function(){
			  // Get all rows with search applied
			  var rows = table.rows({ 'search': 'applied' }).nodes();
			  // Check/uncheck checkboxes for all rows in the table
			  $('input[type="checkbox"]', rows).prop('checked', this.checked);
			});

			// Handle click on checkbox to set state of "Select all" control
			$('#mappingTable tbody').on('change', 'input[type="checkbox"]', function(){
			  // If checkbox is not checked
			  if(!this.checked){
			     var el = $('#example-select-all').get(0);
			     // If "Select all" control is checked and has 'indeterminate' property
			     if(el && el.checked && ('indeterminate' in el)){
			        // Set visual state of "Select all" control
			        // as 'indeterminate'
			        el.indeterminate = true;
			     }
			  }
			});


			$('.removeSelected').on('click', function(e){
				// console.log(table.row($('table tr').has('input:checked')).data());
				table.rows($('table tr').has('input:checked'))
				.remove()
				.draw();
				enableActionPanel(table);
			});

			$('.removeAllBtn').on('click', function(e){
				// console.log(table.row($('table tr').has('input:checked')).data());
				table.rows()
				.remove()
				.draw();
				enableActionPanel(table);
			});

			$('.executeSelected').on('click', function(e){

				console.log(getDataFromTable(table, 4));
				if(getDataFromTable(table, 4).length>0)
					if(hasIssue(table) && !$( ".executeSelected" ).hasClass( "hide" ) && !doneRefreshing){
						alert('Please Refresh the table before execute');
					}else
						post_to_url('/multiAccMapperAction', {
						    multiData:JSON.stringify(getDataFromTable(table, 4))
						}, 'get');
				// row.child( checkForIssueAjax(row.data()) ).show();
			});

			$('.executeIssue').on('click', function(e){
				console.log(getDataFromTable(table, 2));
				if(getDataFromTable(table, 2).length>0)
					if(hasIssue(table) && !$( ".executeIssue" ).hasClass( "hide" ) && !doneRefreshing){
						alert('Please Refresh the table before execute');
					}else
						post_to_url('/multiAccMapperAction', {
						    multiData:JSON.stringify(getDataFromTable(table, 2))
						}, 'get');
				// row.child( checkForIssueAjax(row.data()) ).show();
			});


			$('.executeNoIssue').on('click', function(e){
				console.log(getDataFromTable(table, 1));
				if(getDataFromTable(table, 1).length>0)
					if(hasIssue(table) && !$( ".executeNoIssue" ).hasClass( "hide" ) && !doneRefreshing){
						alert('Please Refresh the table before execute');
					}else
						post_to_url('/multiAccMapperAction', {
						    multiData:JSON.stringify(getDataFromTable(table, 1))
						}, 'get');
				// row.child( checkForIssueAjax(row.data()) ).show();
			});

			$('.executeAll').on('click', function(e){
				$('.refreshTable').trigger('click');
				console.log(getDataFromTable(table));
				// alert(getDataFromTable(table).length)
				setTimeout(() => {  
					if(getDataFromTable(table).length>0)
						if(hasIssue(table) && !$( ".executeAll" ).hasClass( "hide" ) && !doneRefreshing){
							alert('Please Refresh the table before execute');
						}else{
								post_to_url('/multiAccMapperAction', {
								    multiData:JSON.stringify(getDataFromTable(table))
								}, 'get');
								//// code
						} 
					}, 1000);
				sleep(2000).then(() => {
					if(getDataFromTable(table).length>0)
						if(hasIssue(table) && !$( ".executeAll" ).hasClass( "hide" ) && !doneRefreshing){
							alert('Please Refresh the table before execute');
						}else{
								post_to_url('/multiAccMapperAction', {
								    multiData:JSON.stringify(getDataFromTable(table))
								}, 'get');
								//// code
						}
				})

				// row.child( checkForIssueAjax(row.data()) ).show();
			});

			$('.refreshTable').on('click', function(e){
				// var tableDataA = getDataFromTable(table, 5);
				// tableDataA.forEach((element, key) => {
				$("#mainKontent").LoadingOverlay("show", {
				    background  : "rgba(165, 190, 100, 0.5)"
				});

				let trDetails = getDataFromTable(table, 5);
				$.ajax({
			        url: '/chechForIssue',
			        data: {
			            'all': 'true',
			            'trDetails': JSON.stringify(trDetails),
			        },
			        dataType: 'json',
			        success: function ( json ) {
			        	// alert(json)
			        	console.log(json)
			        	table.clear().draw();
			        	table.rows.add(json).draw();

						// Here we might call the "hide" action 2 times, or simply set the "force" parameter to true:
						$("#mainKontent").LoadingOverlay("hide", true);
						doneRefreshing = true;
			        }, 
			        error: function (error) {
			        	alert('error found')
			        	alert(JSON.stringify(error))

						// Here we might call the "hide" action 2 times, or simply set the "force" parameter to true:
						$("#mainKontent").LoadingOverlay("hide", true);
						// alert("doneRefreshing "+doneRefreshing)
						doneRefreshing = true;
			        	// console.log(error)
			        }
			    });

			});

			$('#type').on('change', function(e){
				// alert($(this).val());
				$("#users").prop("disabled", false);
				if($(this).val()=='Master'){
					$('#users').prop('disabled', false);
					$('#account').prop('disabled', false);
					$('#users').select2({
						tags: false,
						theme: 'classic',
						placeholder: 'Select an option',
						maximumSelectionLength: 1
						// dropdownParent: $('.form-horizontal')
					});
					$('#users').val(null).trigger('change');

				}
				if($(this).val()=='Sub'){
					$('#users').prop('disabled', false);
					$('#account').prop('disabled', false);
					$('#users').select2({
						tags: false,
						theme: 'classic',
						placeholder: 'Select an option',
						// maximumSelectionLength: 1
						// dropdownParent: $('.form-horizontal')
					});
				}

				if($(this).val()=='disable_user'){
					$('#account').val(null).trigger('change');
					$('#account').prop('disabled', true);
					$('#users').prop('disabled', false);
				}


				if($(this).val()=='disable_account'){
					$('#users').val(null).trigger('change');
					$('#account').prop('disabled', false);
					$('#users').prop('disabled', true);
					$('#users').select2({
						tags: false,
						theme: 'classic',
						placeholder: 'Select an option',
						maximumSelectionLength: 0
						// dropdownParent: $('.form-horizontal')
					});
				}
			});

			$('.backButton').on('click', function(e){
				parent.history.back();
				// console.log(getDataFromTable(table, 4));
				// row.child( checkForIssueAjax(row.data()) ).show();
			});


			

			$('#addRow').on('click', function(e){
				// alert('Hois')
				if(validateAdd()){
					// var rowData = table.rows().data().toArray();
					// rowData.forEach((element, index) => {
					// 	let usrArr = element[4];
					// })
					let protoFlag = checkProtocols([
							'', 
							$('#startdate').val(), 
							$('#account').val(), 
							$( "#users option:selected" ).toArray().map(item => item.text).join(),
							$('#users').val(), 
							$('#type').val(), 
							'<i class="fa fa-spinner fa-spin fa-3x fa-fw" style="font-size:initial;"></i>',
							'loading',
						])
					if(protoFlag){
						table.row.add( [
							'', 
							$('#startdate').val(), 
							$('#account').val(), 
							$( "#users option:selected" ).toArray().map(item => item.text).join(),
							$('#users').val(), 
							$('#type').val(), 
							'<i class="fa fa-spinner fa-spin fa-3x fa-fw" style="font-size:initial;"></i>',
							'loading',
						] ).draw();
						// $("#users").select2("val", "");
						$('#users').val(null).trigger('change');
						if(table.rows().count()>1)
							doneRefreshing = false;
						enableActionPanel(table);
						checkForIssueAjax(table);
					}
				}else{
					alert('Please Fill-up all the Fields');
				}
			});

			$.arrayIntersect = function(a, b){
			    return $.grep(a, function(i){
			        return $.inArray(i, b) > -1;
			    });
			};
			
		});


		function validateAdd() {
			if($.inArray($('#type').val(), ['disable_user', 'disable_account']) !== -1){
				// alert('disable');
				if($('#startdate').val()!='' && $('#users').val()!='' && $('#type').val()==='disable_user')
					return true;
				else if($('#startdate').val()!='' && $('#account').val()!='' && $('#type').val()==='disable_account')
					return true;
				else
					return false;
			}else{
				// alert('normal');
				if($('#startdate').val()!='' && $('#account').val()!='' && $('#users').val()!='' && $('#type').val()!='')
					return true;
				else
					return false;
			}
		}

		function enableActionPanel(table) {
			if(table.rows().count()>0){
				// $('.executeIssue').attr('disabled' , false);
				$('.executeIssue').removeClass("hide");
				$('.executeAll').removeClass("hide");
				$('.executeNoIssue').removeClass("hide");
				$('.executeSelected').removeClass("hide");
				$('.removeSelected').removeClass("hide");
				// $('.executeAll').attr('disabled' , false);
				// $('.executeNoIssue').attr('disabled' , false);
				// $('.executeSelected').attr('disabled' , false);
				// $('.removeSelected').attr('disabled' , false);
			}else{
				$('.executeIssue').addClass("hide");
				$('.executeAll').addClass("hide");
				$('.executeNoIssue').addClass("hide");
				$('.executeSelected').addClass("hide");
				$('.removeSelected').addClass("hide");

				// $('.executeIssue').attr('disabled' , true);
				// $('.executeAll').attr('disabled' , true);
				// $('.executeNoIssue').attr('disabled' , true);
				// $('.executeSelected').attr('disabled' , true);
				// $('.removeSelected').attr('disabled' , true);

			}
			if(table.rows().count()>1){
				// alert(table.rows().data().toArray());
				// alert(table.rows().data().toArray());
				// $('.refreshTable').attr('disabled' , false);
				$('.refreshTable').removeClass("hide");
			}
			// else
				// $('.refreshTable').attr('disabled' , true);

		}

		function checkForIssueAjax ( table ) {
		    // let div = 'failed';

		    var i = table.rows().count() - 1;
			var rowData = table.row(i).data();
			// data = checkForIssueAjax(data);
			// table.row(i).data(data).draw();

		    $.ajax( {
		        url: '/api/checkForIssue',
		        data: {
		            'startdate': rowData[1],
		            'groupid': rowData[2],
		            'role': rowData[5].toLowerCase(),
		            'newmems[]': rowData[4],
		            'trDetails': JSON.stringify(getDataFromTable(table, 5)),
		        },
		        dataType: 'json',
		        success: function ( json ) {
		        	// alert('He he! Success')
		        	if(typeof json === "number" && json==0){
			            rowData[6] = '<i class="fa fa-check" aria-hidden="true" style="color:green;"></i>';
			            rowData[7] = 'success';
						table.row(i).data(rowData).draw();
		        	}else if(typeof json === "number" && json>0){
		        		// rowData[6] = '<i class="fa fa-ban" aria-hidden="true" style="color:red;"></i>';
		        		rowData[6] = '<i class="fa fa-exclamation-circle" aria-hidden="true" style="color:#e15f00;">'+json+'</i>';
			            rowData[7] = 'issue';
								table.row(i).data(rowData).draw();

		        	}else if(typeof json != "number" && json.length<=100){
								rowData[6] = json;
					            rowData[7] = 'issue';
								table.row(i).data(rowData).draw();

		        	}else{
						rowData[6] = '<i class="fa fa-ban" aria-hidden="true" style="color:red;"></i>';
			            rowData[7] = 'issue';
						table.row(i).data(rowData).draw();
		        	}
		        }, 
		        error: function (error) {
		        	console.log(error)
		        	// console.log(error.responseText)
		        	if(typeof error.responseText != "number" && error.responseText.length<=100){
		        		rowData[6] = error.responseText;
					            rowData[7] = 'cancelled';
								table.row(i).data(rowData).draw();
		        	}else{
								rowData[6] = '<i class="fa fa-ban" aria-hidden="true" style="color:red;"></i>';
					            rowData[7] = 'cancelled';
								table.row(i).data(rowData).draw();
		        	}
		        }
		    } );
		 
		}

		function hasIssue(tab1) {
			let datas = tab1.rows().data().toArray();
			let flagg = false;
			datas.forEach((element, key) => {
				if(element[7]!='success'){
					flagg = true;
			// 		break;
				}
			});
			return flagg;
		}

		function checkProtocols(arr) {
			// alert('Proto')
			if(table.rows().count()>0){
			// alert('Proto1')
				let tableData = getDataFromTable(table, 5)
				let msg = [];
				tableData.forEach((element, key) => {
			// alert(element['startdate']+' | '+arr[1])
						if(element['startdate'] === arr[1]){
			// alert(element['mappedTo']+' | '+arr[2]+' | '+element['role']+' | '+arr[5])
							if(element['mappedTo'] === arr[2] && element['role'] === arr[5].toLowerCase()){
								msg.push('Duplicate Master Entry in a same day for an Account is not allowed');
							}

							if($.arrayIntersect(element['user'], arr[4]).length>0){
								msg.push('Duplicate User Mapping in a same day is not allowed');
							}
						}
				});
				if(msg.length>0){
					alert(msg.join("\n"));
					return false;
				}else
					return true;

			}else{
				return true;
			}
		}

		function getDataFromTable(table, flag = 0){
			let tableKeyArray = [];
			let datas = table.rows().data().toArray();
			switch(flag){
				case 1:
					datas.forEach((element, key) => {
						if(element[7]==='success'){
							// console.log(element);
							// alert(key)
							tableKeyArray[key] = element;
						}
					});
					break;
				case 2:
					datas.forEach((element, key) => {
						if(element[7]==='issue'){
							// alert(key)
							// console.log(element);
							tableKeyArray[key] = element;
						}
					});
					break;
				case 3:
					datas.forEach((element, key) => {
						if(element[7]==='loading'){
							// alert(key)
							// console.log(element);
							tableKeyArray[key] = element;
						}
					});
					break;
				case 4:
					let tableKeyArray2 = table.rows($('table tr').has('input:checked')).data().toArray();
					datas.forEach((element, key) => {
						tableKeyArray2.forEach((element1, key1) => {
							if (JSON.stringify(element1) == JSON.stringify(element)){
								// alert(key)
								// console.log(element);
								tableKeyArray[key] = element;
							}
						});
					});
					// return datas;
					break;

				case 5:
					datas.forEach((element, key) => {
						// if(element[7]!='cancelled'){
						// 	// alert(key)
						// 	// console.log(element);
						// 	tableKeyArray[key] = element;
						// }
					});

					datas.forEach((element, key) => {
						// if(element[7]!='cancelled'){
						// 	// alert(key)
						// 	// console.log(element);
						// 	tableKeyArray[key] = element;
						// }
					// console.log(element);
						tmp = Object.create(element);
						// tmp.splice(0, 1);
						// tmp.splice(3, 1);
						// tmp.splice(6, 1);
						// if(tmp.length==6)
						// 	tmp.splice(4, 1);
						tableKeyArray[key] = {
							'id' : key,
							'startdate' : tmp[1],
							'user' : tmp[4],
							'mappedTo' : tmp[2],
							'role' : tmp[5].toLowerCase(),
							'status' : tmp[7].toLowerCase(),
						};
					});
					console.log(tableKeyArray);
					// console.log(JSON.stringify(Object.values(tableKeyArray)));
					break;


					tableKeyArray = datas;
					break;
				default:
					datas.forEach((element, key) => {
						if(element[7]!='cancelled'){
							// alert(key)
							// console.log(element);
							tableKeyArray[key] = element;
						}
					});
					break;
			}
			tableKeyArray = tableKeyArray.filter(item => item);
			return tableKeyArray;
		}

		function submitForm(dataArray){
			var form = $(document.createElement('form'));
		    $(form).attr("action", "/multiAccMapperAction");
		    $(form).attr("method", "POST");

		    var input = $("<input>").attr("type", "hidden").attr("name", "multiacc").val(JSON.stringify(dataArray));
		    $(form).append($(input));


		    var input = $("<input>").attr("type", "hidden").attr("name", "_csrf").val("{{csrf_token()}}");
		    $(form).append($(input));
		    $(form).submit();
		}

		function post_to_url(path, params, method) {
		    method = method || "post";

		    var form = document.createElement("form");
		    form.setAttribute("method", method);
		    form.setAttribute("action", path);
		    var hiddenField = document.createElement("input");
            hiddenField.setAttribute("type", "hidden");
            hiddenField.setAttribute("name", 'token');
            hiddenField.setAttribute("value", '{{csrf_token()}}');
            form.appendChild(hiddenField);
		    var hiddenField2 = document.createElement("input");
            hiddenField2.setAttribute("type", "hidden");
            hiddenField2.setAttribute("name", 'trDetails');
            hiddenField2.setAttribute("value", JSON.stringify(getDataFromTable(table, 5)));
            // form.appendChild(hiddenField2);

		    for(var key in params) {
		        if(params.hasOwnProperty(key)) {
		            var hiddenField = document.createElement("input");
		            hiddenField.setAttribute("type", "hidden");
		            hiddenField.setAttribute("name", key);
		            hiddenField.setAttribute("value", params[key]);

		            form.appendChild(hiddenField);
		         }
		    }

		    document.body.appendChild(form);
		    form.submit();
		}


	</script>

</body>
</html>