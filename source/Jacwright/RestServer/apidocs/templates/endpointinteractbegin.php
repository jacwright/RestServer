					<div class="endpoint-interact">
						<h3>Try it out!</h3>
						<script type="text/javascript"><?php readfile(__DIR__ . "/interact.js"); ?></script>
						<form id="tryit" onsubmit="return demoRequest(this)">
							<input type="hidden" name="i-method" value="<?php echo $method; ?>" />
							<input type="hidden" name="i-endpoint" value="<?php echo $endpoint; ?>" />
							<input type="hidden" name="i-base" value="<?php echo $apibase; ?>" />
