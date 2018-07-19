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
						<div class="form-group">
							<div class="col-sm-offset-2 col-sm-10">
								<div class="checkbox">
									<label><input type="checkbox" name="consent_login_direct" {!! $login_direct !!}> Anyone signed-in directly to this Authorization Server sees Everything</label>
									<button type="button" class="btn btn-info" data-toggle="collapse" data-target="#login_direct_detail" style="margin-left:20px">Details</button>
								</div>
								<div id="login_direct_detail" class="collapse">
									<p>Any party that you invite directly to be an authorized user to this Authorization Server has access to your Protected Health Information (PHI).  For example, this can be a spouse, partner, family members, guardian, or attorney.</p>
								</div>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-offset-2 col-sm-10">
								<div class="checkbox">
									<label><input type="checkbox" name="consent_public_publish_directory" {!! $public_publish_directory !!}> Anyone can see where this resource is located in a Directory</label>
									<button type="button" class="btn btn-info" data-toggle="collapse" data-target="#public_publish_directory_detail" style="margin-left:20px">Details</button>
								</div>
								<div id="public_publish_directory_detail" class="collapse">
									<p>Any party that has access to a Directory that you participate in can see where this resource is found.</p>
								</div>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-offset-2 col-sm-10">
								<div class="checkbox">
									<label><input type="checkbox" name="consent_private_publish_directory" {!! $private_publish_directory !!}> Only previously authorized users can see where this resource is located in a Directory</label>
									<button type="button" class="btn btn-info" data-toggle="collapse" data-target="#private_publish_directory_detail" style="margin-left:20px">Details</button>
								</div>
								<div id="private_publish_directory_detail" class="collapse">
									<p>Only previously authorized users that has access to a Directory that you participate in can see where this resource is found.</p>
								</div>
							</div>
						</div>

						<div class="form-group">
							<div class="col-sm-offset-2 col-sm-10">
								<div class="checkbox">
									<label><input type="checkbox" name="consent_any_npi" {!! $any_npi !!}> Anyone that has a National Provider Identifier (NPI) sees these Resources"</label>
									<button type="button" class="btn btn-info" data-toggle="collapse" data-target="#any_npi_detail" style="margin-left:20px">Details</button>
								</div>
								<div id="any_npi_detail" class="collapse">
									<p>All individual HIPAA covered healthcare providers have a National Provider Identifier, including:</p>
									<ul>
										<li>Physicians</li>
										<li>Pharmacists</li>
										<li>Physician assistants</li>
										<li>Midwives</li>
										<li>Nurse practitioners</li>
										<li>Nurse anesthetists</li>
										<li>Dentsits</li>
										<li>Denturists</li>
										<li>Chiropractors</li>
										<li>Podiatrists</li>
										<li>Naturopathic physicians</li>
										<li>Clinical social workers</li>
										<li>Professional counselors</li>
										<li>Psychologists</li>
										<li>Physical therapists</li>
										<li>Occupational therapists</li>
										<li>Pharmacy technicians</li>
										<li>Atheletic trainers</li>
									</ul>
									<p>By setting this as a default, you allow any healthcare provider, known or unknown at any given time to access and edit your protected health information.</p>
								</div>
							</div>
						</div>

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
