@extends('layouts.app')

@section('content')
<div class="container">
	<div class="row">
		<div class="col-md-8 col-md-offset-2">
			<div class="panel panel-default">
				<div class="panel-heading">
					<h4 class="panel-title" style="height:35px;display:table-cell !important;vertical-align:middle;">Check your email</h4>
				</div>
				<div class="panel-body">
					<div style="text-align: center;">
					  <i class="fa fa-envelope fa-5x" aria-hidden="true" style="margin:20px;text-align: center;"></i>
					</div>
					<div class="alert alert-success">
						<p>An email was sent to you at {!! $email !!}</p>
						<p>It has a magic link that'll log you in</p>
						<p>If you haven't recieved an email after a minute (check your spam folder first, just in case), <a href="{{ route('login') }}">click here to try again</a>.</p>
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
