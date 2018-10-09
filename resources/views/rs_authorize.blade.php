@extends('layouts.app')

@section('content')
<div class="container">
	<div class="row">
		<div class="col-md-10 col-md-offset-1">
			<div class="panel panel-default">
				<div class="panel-heading">
					<div class="container-fluid panel-container">
						<div class="col-xs-6 col-md-9 text-left">
							<h4 class="panel-title" style="height:35px;display:table-cell !important;vertical-align:middle;">Resource Registration Consent</h4>
						</div>
						<div class="col-xs-3 text-right">
							@if (isset($back))
								{!! $back !!}
							@endif
						</div>
					</div>
				</div>
				<div class="panel-body">
					@if (isset($set))
						<form class="form-horizontal" role="form" method="POST" action="{{ route('rs_authorize_action', [$set]) }}">
					@else
						<form class="form-horizontal" role="form" method="POST" action="{{ route('rs_authorize_action') }}">
					@endif
						<div style="text-align: center;">
						  {!! $content !!}
						</div>
						{!! $policies !!}
						<div class="form-group">
							<div class="col-md-6 col-md-offset-3">
								<button type="submit" class="btn btn-success btn-block" name="submit" value="allow">
									@if (isset($set))
										<i class="fa fa-btn fa-check"></i> Save
									@else
										<i class="fa fa-btn fa-check"></i> Allow Registration
									@endif
								</button>
								<button type="submit" class="btn btn-danger btn-block" name="submit" value="deny">
									@if (isset($set))
										<i class="fa fa-btn fa-times"></i> Go Back
									@else
										<i class="fa fa-btn fa-times"></i> Cancel Registration
									@endif
								</button>
								@if (isset($set))
								<button type="submit" class="btn btn-warning btn-block" id="disconnect" name="submit" value="disconnect">
									<i class="fa fa-btn fa-chain-broken"></i> Disconnect Resource
								</button>
								@endif
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
		$('#disconnect').css('cursor', 'pointer').click(function() {
			var r = confirm('Are you sure you want to disconnect this resource?');
			if (r === true) {
				return true;
			} else {
				return false;
			}
		});
	});
</script>
@endsection
