@extends('layouts.app')

@section('content')
<div class="container">
	<div class="row">
		<div class="col-md-10 col-md-offset-1">
			<div class="panel panel-default">
				<div class="panel-heading">Resource Registration Consent</div>
				<div class="panel-body">
					<!-- <form class="form-horizontal" role="form" method="POST" action="{{ URL::to('rs_authorize_action') }}"> -->
					<form class="form-horizontal" role="form" method="POST" action="{{ URL::to('test1') }}">
						<div style="text-align: center;">
						  {!! $content !!}
						</div>
						<div class="form-group">
							<div class="col-sm-offset-2 col-sm-10">
								<div class="checkbox">
									<label><input type="checkbox" name="consent_login_direct" checked> Anyone signed-in directly to this Authorization Server sees Everything</label>
								</div>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-offset-2 col-sm-10">
								<div class="checkbox">
									<label><input type="checkbox" name="consent_login_md_nosh" checked> Anyone signed in via mdNOSH Gateway sees Everything</label>
								</div>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-offset-2 col-sm-10">
								<div class="checkbox">
									<label><input type="checkbox" name="consent_login_google" checked> Anyone signed in via Google ID gets an email from me and can see Emergency Information</label>
								</div>
							</div>
						</div>
						<div class="form-group">
							<div class="col-md-6 col-md-offset-3">
								<button type="submit" class="btn btn-success btn-block" name="submit" value="allow">
									<i class="fa fa-btn fa-check"></i> Allow
								</button>
								<button type="submit" class="btn btn-danger btn-block" name="submit" value="deny">
									<i class="fa fa-btn fa-times"></i> Deny
								</button>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection

@section('view.scripts')
<script type="text/javascript">
	$(document).ready(function() {
	});
</script>
@endsection
