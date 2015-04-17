( function($) {

	$.ffmpegExtract = function(opts) {
		var defaults = {

			debug: false,

			serverFilesFolder: 'ffmpeg-extract/server/',
			serverPollingFile: 'ffmpeg-extract.php',

			pollingDelay: 2000,
			firstPollingDelay: 1000,
			onProgress: function(progressData) {},
			onStart: function() {},
			onFinish: function() {},
			sourceFilename: null
		};
		var settings = $.extend(defaults, opts);

		if( ! settings.sourceFilename ) {
			console.error('A source video must be specified');
			return;
		}

		var poller = function() {
			$.getJSON(settings.serverFilesFolder + settings.serverPollingFile, function(data) {
				if( data && data.status ) {
					switch(data.status) {
						case 'working':
							settings.onProgress(data);
							setTimeout(poller, settings.pollingDelay);
							break;
						case 'finished':
							settings.onProgress(data);

							for(var i = 0; i < data.files.length; i++) {
								data.files[i] = settings.serverFilesFolder + data.files[i];
							}

							settings.onFinish(data);
							break;
						case 'error':
							console.error('FFMPEG Error');
							console.warn(data.message);
							console.info(data.data);
							break;
					}
				}
			});
		}

		var start = function() {
			$.getJSON(settings.serverFilesFolder + settings.serverPollingFile, {'start-source-video': settings.sourceFilename}, function(data) {
				if( data && data.status == 'started' ) {
					settings.onStart();

					setTimeout(poller, settings.firstPollingDelay);
				}

				if( data && data.status == 'error' ) {
					console.error('FFMPEG Error');
					console.warn(data.message);
					console.info(data.data);
				}
			});
		}

		start();
	}
}) (jQuery);