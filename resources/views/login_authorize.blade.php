@extends('layouts.app')

@section('content')
<div class="container">
	<div class="row">
		<div class="col-md-10 col-md-offset-1">
			<div class="panel panel-default">
				<div class="panel-heading">Consent</div>
				<div class="panel-body">
					<div style="text-align: center;">
					  {!! $permissions !!}
					</div>
					<div class="form-group">
						<div class="col-md-6 col-md-offset-3">
							<a href="{{ URL::to('login_authorize_action') }}/yes" class="btn btn-success btn-block" role="button"><i class="fa fa-btn fa-check"></i> Allow</a>
							<a href="{{ URL::to('login_authorize_action') }}/no" class="btn btn-danger btn-block" role="button"><i class="fa fa-btn fa-times"></i> Deny</a>
						</div>
					</div>
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
