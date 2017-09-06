@extends('layouts.app')

@section('view.stylesheet')
	<link rel="stylesheet" href="{{ asset('assets/css/toastr.min.css') }}">
@endsection

@section('content')
<div class="container">
	<div class="row">
		<div class="col-md-10 col-md-offset-1">
			<div class="panel panel-default">
				<div class="panel-heading">
					<div class="container-fluid panel-container">
						<div class="col-xs-6 col-md-9 text-left">
							<h4 class="panel-title" style="height:35px;display:table-cell !important;vertical-align:middle;">{!! $title !!}</h4>
						</div>
						<div class="col-xs-3 text-right">
							@if (isset($back))
								{!! $back !!}
							@endif
						</div>
					</div>

				</div>
				<div class="panel-body">
					@if (isset($message_action))
						<div class="alert alert-success">
							<strong>{!! $message_action !!}</strong>
						</div>
					@endif
					@if (isset($pnosh))
						<div class="alert alert-danger">
							<h4>Register your resources!</h4>
							<p>Install and register your pNOSH, the one true health record.</p>
							<p><a href="{{ $pnosh_url }}">Get Started</a></p>
						</div>
					@endif
					@if (isset($mdnosh))
						<div class="alert alert-danger">
							<h4>Get connected!</h4>
							<p>Get connected to the mdNOSH Electronic Health Record System.</p>
							<p>With mdNOSH, your authorization server can connect to thousands of physicians who may be involved in your care.</p>
							<p><a href="http://noshchartingsystem.com/oidc/uma_register">Get Started</a></p>
						</div>
					@endif
					{!! $content !!}
					@if (isset($blockchain_table))
						<div class="form-group">
							<div class="col-md-6 col-md-offset-3">
								<button type="button" class="btn btn-info btn-block" data-toggle="collapse" data-target="#blockchain_detail">{{ $blockchain_count }} Blockchain Transactions</button>
							</div>
							<div class="col-md-12">
							<div id="blockchain_detail" class="collapse" style="margin-top:20px;">
								<p><strong>Last 10 transactions:</strong></p>
								{!! $blockchain_table !!}
							</div>
						</div>
					@endif
				</div>
			</div>
		</div>
	</div>
</div>
<div class="modal" id="fhirModal" role="dialog">
	<div class="modal-dialog">
		<!-- Modal content-->
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Patient Portal Settings</h5>
			</div>
			<div class="modal-body" style="height:35vh;overflow-y:auto;">
				<form id="fhir_form" class="form-horizontal form" role="form" method="POST">
					<input type="hidden" name="endpoint_uri" id="endpoint_uri">
					<div class="form-group" id="until_div">
						<label for="fhir_username" class="col-md-3 control-label">Username</label>
						<div class="col-md-8">
							<input type="text" id="fhir_username" class="form-control" name="username" value="">
						</div>
					</div>
					<div class="form-group" id="until_div">
						<label for="fhir_password" class="col-md-3 control-label">Password</label>
						<div class="col-md-8">
							<input type="password" id="fhir_password" class="form-control" name="password" value="">
						</div>
					</div>
					<div class="form-group">
						<div class="col-md-8 col-md-offset-4">
							<button type="submit" class="btn btn-success" style="margin:10px">
								<i class="fa fa-btn fa-save"></i> Save
							</button>
							<button type="button" class="btn btn-danger" style="margin:10px" id="fhir_cancel">
								<i class="fa fa-btn fa-ban"></i> Cancel
							</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
@endsection

@section('view.scripts')
<script src="{{ asset('assets/js/toastr.min.js') }}"></script>
<script type="text/javascript">
	$(document).ready(function() {
		toastr.options = {
            'closeButton': true,
            'debug': false,
            'newestOnTop': true,
            'progressBar': true,
            'positionClass': 'toast-bottom-full-width',
            'preventDuplicates': false,
            'showDuration': '300',
            'hideDuration': '1000',
            'timeOut': '5000',
            'extendedTimeOut': '1000',
            'showEasing': 'swing',
            'hideEasing': 'linear',
            'showMethod': 'fadeIn',
            'hideMethod': 'fadeOut'
        };
		$("#remove_permissions_button").css('cursor', 'pointer').on('click', function() {
			return confirm('Removing all permissions cannot be undone!');
		});
		$(".pnosh_link").css('cursor', 'pointer').on('click', function(e){
			var link = $(this).attr('nosh-link');
			window.open(link);
			e.preventDefault();
		});
		$(".pnosh_copy").css('cursor', 'pointer').on('click', function(e){
			var copy = $(this).attr('hie-val');
			var $temp = $("<input>");
			$("body").append($temp);
			$temp.val(copy).select();
			document.execCommand("copy");
			$temp.remove();
			if ($(this).hasClass('fa-clone')) {
				toastr.success('Username copied');
			} else {
				toastr.success('Password copied');
			}
			e.preventDefault();
		});
		$(".pnosh_copy_set").click(function(e){
			e.preventDefault();
			var uri = $(this).attr('hie-val');
			var username = $(this).next().next().attr('hie-val');
			var password = $(this).next().next().next().next().attr('hie-val');
			$('#endpoint_uri').val(uri);
			$('#fhir_username').val(username);
			$('#fhir_password').val(password);
			$('#fhirModal').modal('show');
		});
		$(document).on('submit', '#fhir_form', function(event) {
            event.preventDefault();
            var formData = $(this).serialize();
			$.ajax({
				type: "POST",
				url: "{{ route('fhir_edit') }}",
				data: formData,
				beforeSend: function(request) {
					return request.setRequestHeader("X-CSRF-Token", $("meta[name='csrf-token']").attr('content'));
				}
			}).done(function(response) {
				toastr.success(response);
				$('#fhirModal').modal('hide');
				$('#fhir_username').val('');
				$('#fhir_password').val('');
				$('#endpoint_uri').val('');
				location.reload(true);
            });
        });
		$("#fhir_cancel").click(function(){
			$('#fhirModal').modal('hide');
			$('#fhir_username').val('');
			$('#fhir_password').val('');
			$('#endpoint_uri').val('');
		});
		$('[data-toggle="tooltip"]').tooltip();
	});
</script>
@endsection
