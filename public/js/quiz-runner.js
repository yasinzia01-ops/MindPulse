/**
 * Frontend quiz runner used by both the [mindpulse_quiz] shortcode and
 * the Elementor widget. Stage machine: Intro -> (lead capture) -> Questions
 * -> Processing -> submit -> (Preview -> lead capture -> Checkout) or
 * Report. Every structured page (intro/processing/preview/email_capture/
 * checkout/report) is optional per-quiz (see MP_CPT::get_quiz_data()) and
 * simply skipped when not enabled, so a plain quiz behaves exactly as
 * before.
 */
( function () {
	'use strict';

	function el( tag, cls, html ) {
		var node = document.createElement( tag );
		if ( cls ) node.className = cls;
		if ( html !== undefined ) node.innerHTML = html;
		return node;
	}

	function api( path, body ) {
		return fetch( MindPulseConfig.restUrl + path, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': MindPulseConfig.nonce,
			},
			body: JSON.stringify( body ),
		} ).then( function ( res ) {
			return res.json();
		} );
	}

	function apiGet( path, params ) {
		var query = Object.keys( params )
			.map( function ( key ) {
				return encodeURIComponent( key ) + '=' + encodeURIComponent( params[ key ] );
			} )
			.join( '&' );

		return fetch( MindPulseConfig.restUrl + path + '?' + query, {
			headers: { 'X-WP-Nonce': MindPulseConfig.nonce },
		} ).then( function ( res ) {
			return res.json();
		} );
	}

	function getUrlParam( name ) {
		var match = new RegExp( '[?&]' + name + '=([^&]*)' ).exec( window.location.search );
		return match ? decodeURIComponent( match[ 1 ].replace( /\+/g, ' ' ) ) : '';
	}

	function escapeHtml( str ) {
		var div = document.createElement( 'div' );
		div.textContent = str == null ? '' : String( str );
		return div.innerHTML;
	}

	var DEFAULT_CLASSIFICATIONS = [
		{ min: 130, max: 999, label: 'Exceptionally Gifted' },
		{ min: 120, max: 129, label: 'Superior' },
		{ min: 110, max: 119, label: 'High Average' },
		{ min: 90, max: 109, label: 'Average' },
		{ min: 80, max: 89, label: 'Low Average' },
		{ min: -999, max: 79, label: 'Below Average' },
	];

	// Same erf approximation the reference funnel uses to turn a raw
	// percent-correct into a population percentile display.
	function erf( x ) {
		var sign = x < 0 ? -1 : 1;
		x = Math.abs( x );
		var t = 1 / ( 1 + 0.3275911 * x );
		var y = 1 - ( ( ( ( ( 1.061405429 * t - 1.453152027 ) * t ) + 1.421413741 ) * t - 0.284496736 ) * t + 0.254829592 ) * t * Math.exp( -x * x );
		return sign * y;
	}

	function percentToIq( percent ) {
		return Math.round( 55 + ( percent / 100 ) * 90 );
	}

	function iqToPercentile( iq ) {
		var z = ( iq - 100 ) / 15;
		var c = 0.5 * ( 1 + erf( z / Math.sqrt( 2 ) ) );
		return Math.max( 1, Math.min( 99, Math.round( c * 100 ) ) );
	}

	function classificationFor( iq, labels ) {
		var list = labels && labels.length ? labels : DEFAULT_CLASSIFICATIONS;
		for ( var i = 0; i < list.length; i++ ) {
			if ( iq >= list[ i ].min && iq <= list[ i ].max ) {
				return list[ i ].label;
			}
		}
		return '';
	}

	function initQuiz( container ) {
		var quizId = container.getAttribute( 'data-quiz-id' );
		var partnerKey = container.getAttribute( 'data-partner-key' ) || '';
		var quiz;
		try {
			quiz = JSON.parse( container.getAttribute( 'data-quiz' ) );
		} catch ( e ) {
			container.innerHTML = '<p>Unable to load quiz.</p>';
			return;
		}

		var state = {
			step: 0, // 0 = lead capture (if enabled up front), 1..n = questions
			answers: {},
			leadId: 0,
			name: '',
			email: '',
			lastResult: null,
		};

		var leadCaptureFirst = !! ( quiz.settings && quiz.settings.lead_capture_before );
		var activeInterval = null;

		function clearActiveInterval() {
			if ( activeInterval ) {
				clearInterval( activeInterval );
				activeInterval = null;
			}
		}

		maybeShowSubmission();

		function maybeShowSubmission() {
			var submissionId = getUrlParam( 'mp_submission' );

			if ( ! submissionId ) {
				maybeResume();
				return;
			}

			container.innerHTML = '<div class="mp-quiz__loading">Loading your result…</div>';

			apiGet( '/submission/' + encodeURIComponent( submissionId ), {} ).then( function ( res ) {
				if ( ! res || res.code ) {
					maybeResume();
					return;
				}
				handleResult( res );
			} ).catch( function () {
				maybeResume();
			} );
		}

		function maybeResume() {
			var resumeToken = getUrlParam( 'mp_resume' );
			var resumeQuizId = getUrlParam( 'mp_quiz' );

			if ( ! resumeToken || String( resumeQuizId ) !== String( quizId ) ) {
				renderIntroOrStart();
				return;
			}

			container.innerHTML = '<div class="mp-quiz__loading">Resuming your quiz…</div>';

			apiGet( '/resume', { token: resumeToken, quiz_id: quizId } ).then( function ( res ) {
				if ( ! res || res.code ) {
					renderIntroOrStart();
					return;
				}

				state.leadId = res.lead_id;
				state.name = res.name || '';
				state.email = res.email || '';
				state.answers = res.answers || {};
				state.step = res.last_step || 0;

				renderQuestionFlow();
			} ).catch( function () {
				renderIntroOrStart();
			} );
		}

		/* ---------------- Stage 1: Intro ---------------- */

		function renderIntroOrStart() {
			if ( quiz.intro && quiz.intro.enabled ) {
				renderIntro();
			} else {
				renderQuestionFlow();
			}
		}

		function renderIntro() {
			clearActiveInterval();
			container.innerHTML = '';
			var wrap = el( 'div', 'mp-quiz__intro' );

			if ( quiz.intro.logo_url ) {
				var logo = el( 'img', 'mp-quiz__intro-logo' );
				logo.src = quiz.intro.logo_url;
				wrap.appendChild( logo );
			}

			wrap.appendChild( el( 'h1', '', escapeHtml( quiz.intro.title ) ) );
			if ( quiz.intro.subtitle ) {
				wrap.appendChild( el( 'p', 'mp-quiz__intro-subtitle', escapeHtml( quiz.intro.subtitle ) ) );
			}
			if ( quiz.intro.meta_text ) {
				wrap.appendChild( el( 'p', 'mp-quiz__intro-meta', escapeHtml( quiz.intro.meta_text ) ) );
			}

			var tips = ( quiz.intro.tips || [] ).filter( Boolean );
			if ( tips.length ) {
				var tipsWrap = el( 'div', 'mp-quiz__intro-tips' );
				tips.forEach( function ( tip ) {
					tipsWrap.appendChild( el( 'div', 'mp-quiz__intro-tip', escapeHtml( tip ) ) );
				} );
				wrap.appendChild( tipsWrap );
			}

			var button = el( 'button', 'mp-btn mp-btn--primary', escapeHtml( quiz.intro.button_text || 'Start' ) );
			button.type = 'button';
			button.addEventListener( 'click', renderQuestionFlow );
			wrap.appendChild( button );

			container.appendChild( wrap );
		}

		/* ---------------- Stage 2: lead capture (pre) + Questions ---------------- */

		function renderQuestionFlow() {
			clearActiveInterval();
			container.innerHTML = '';

			if ( leadCaptureFirst && state.step === 0 ) {
				renderLeadCapture( { onDone: function () {
					state.step++;
					renderQuestionFlow();
				} } );
				return;
			}

			var questionIndex = state.step - ( leadCaptureFirst ? 1 : 0 );

			if ( questionIndex < quiz.questions.length ) {
				renderQuestion( quiz.questions[ questionIndex ], questionIndex );
				return;
			}

			var deferEmailToStructuredCapture = !! ( quiz.email_capture && quiz.email_capture.enabled );

			if ( ! leadCaptureFirst && ! state.email && ! deferEmailToStructuredCapture ) {
				renderLeadCapture( { onDone: renderQuestionFlow } );
				return;
			}

			renderProcessingOrSubmit();
		}

		function renderLeadCapture( opts ) {
			var wrap = el( 'div', 'mp-quiz__lead' );
			wrap.appendChild( el( 'h3', '', 'Enter your details to begin' ) );

			var nameInput = el( 'input' );
			nameInput.type = 'text';
			nameInput.placeholder = 'Your name';
			nameInput.className = 'mp-input mp-input--name';
			nameInput.value = state.name || '';

			var emailInput = el( 'input' );
			emailInput.type = 'email';
			emailInput.placeholder = 'Your email';
			emailInput.required = true;
			emailInput.className = 'mp-input mp-input--email';
			emailInput.value = state.email || '';

			var button = el( 'button', 'mp-btn mp-btn--primary', 'Start' );
			button.type = 'button';
			button.addEventListener( 'click', function () {
				if ( ! emailInput.value || emailInput.validity.typeMismatch ) {
					emailInput.classList.add( 'mp-input--error' );
					return;
				}
				state.name = nameInput.value;
				state.email = emailInput.value;
				captureLead();
				opts.onDone();
			} );

			wrap.appendChild( nameInput );
			wrap.appendChild( emailInput );
			wrap.appendChild( button );
			container.innerHTML = '';
			container.appendChild( wrap );
		}

		/**
		 * Structured version of the lead form, used after the paywall
		 * preview (post-questions email capture) when quiz.email_capture
		 * is enabled — same underlying /lead-capture call, richer copy.
		 */
		function renderStructuredEmailCapture( onDone ) {
			clearActiveInterval();
			var cfg = quiz.email_capture;
			container.innerHTML = '';
			var wrap = el( 'div', 'mp-quiz__email-capture' );

			wrap.appendChild( el( 'h2', '', escapeHtml( cfg.headline || 'Where should we send your results?' ) ) );
			if ( cfg.subheadline ) {
				wrap.appendChild( el( 'p', 'mp-quiz__ec-sub', escapeHtml( cfg.subheadline ) ) );
			}

			var nameInput = el( 'input' );
			nameInput.type = 'text';
			nameInput.placeholder = 'Your name';
			nameInput.className = 'mp-input mp-input--name';
			nameInput.value = state.name || '';

			var emailInput = el( 'input' );
			emailInput.type = 'email';
			emailInput.placeholder = 'Your email';
			emailInput.required = true;
			emailInput.className = 'mp-input mp-input--email';
			emailInput.value = state.email || '';

			wrap.appendChild( nameInput );
			wrap.appendChild( emailInput );

			if ( cfg.stat1_label || cfg.stat2_label ) {
				var stats = el( 'div', 'mp-quiz__ec-stats' );
				if ( cfg.stat1_label ) {
					stats.appendChild( el( 'div', 'mp-quiz__ec-stat', '<span class="mp-quiz__ec-stat-label">' + escapeHtml( cfg.stat1_label ) + '</span><span class="mp-quiz__ec-stat-value">' + escapeHtml( cfg.stat1_value ) + '</span>' ) );
				}
				if ( cfg.stat2_label ) {
					stats.appendChild( el( 'div', 'mp-quiz__ec-stat', '<span class="mp-quiz__ec-stat-label">' + escapeHtml( cfg.stat2_label ) + '</span><span class="mp-quiz__ec-stat-value">' + escapeHtml( cfg.stat2_value ) + '</span>' ) );
				}
				wrap.appendChild( stats );
			}

			var button = el( 'button', 'mp-btn mp-btn--primary', escapeHtml( cfg.button_text || 'Continue' ) );
			button.type = 'button';
			button.addEventListener( 'click', function () {
				if ( ! emailInput.value || emailInput.validity.typeMismatch ) {
					emailInput.classList.add( 'mp-input--error' );
					return;
				}
				state.name = nameInput.value;
				state.email = emailInput.value;
				captureLead();
				onDone();
			} );
			wrap.appendChild( button );

			if ( cfg.trust_text ) {
				wrap.appendChild( el( 'p', 'mp-quiz__ec-trust', escapeHtml( cfg.trust_text ) ) );
			}

			container.appendChild( wrap );
		}

		function renderQuestion( question, index ) {
			container.innerHTML = '';
			var wrap = el( 'div', 'mp-quiz__question' );
			wrap.appendChild( el( 'div', 'mp-quiz__progress', ( index + 1 ) + ' / ' + quiz.questions.length ) );
			wrap.appendChild( el( 'h3', '', escapeHtml( question.text ) ) );

			var list = el( 'div', 'mp-quiz__options' );
			( question.options || [] ).forEach( function ( option, optIndex ) {
				var btn = el( 'button', 'mp-btn mp-btn--option', escapeHtml( option.label ) );
				btn.type = 'button';
				btn.addEventListener( 'click', function () {
					state.answers[ question.id ] = optIndex;
					state.step++;
					captureLead();
					renderQuestionFlow();
				} );
				list.appendChild( btn );
			} );

			wrap.appendChild( list );
			container.appendChild( wrap );
		}

		function captureLead() {
			if ( ! state.email ) {
				return;
			}
			api( '/lead-capture', {
				quiz_id: quizId,
				name: state.name,
				email: state.email,
				step: state.step,
				answers: state.answers,
				partner_key: partnerKey,
				// Present once the quiz has already been scored anonymously
				// (deferred email capture, post-preview) so the server can
				// attach this email back onto that submission.
				submission_id: state.lastResult ? state.lastResult.submission_id : 0,
			} ).then( function ( res ) {
				if ( res && res.lead_id ) {
					state.leadId = res.lead_id;
				}
			} ).catch( function () {} );
		}

		/* ---------------- Stage 3: Processing ---------------- */

		function renderProcessingOrSubmit() {
			if ( quiz.processing && quiz.processing.enabled ) {
				renderProcessing();
			} else {
				submitQuiz();
			}
		}

		function renderProcessing() {
			container.innerHTML = '';
			var wrap = el( 'div', 'mp-quiz__processing' );
			wrap.appendChild( el( 'h2', '', escapeHtml( quiz.processing.title || 'Scoring your test' ) ) );
			if ( quiz.processing.subtitle ) {
				wrap.appendChild( el( 'p', '', escapeHtml( quiz.processing.subtitle ) ) );
			}
			var bar = el( 'div', 'mp-quiz__processing-bar' );
			var fill = el( 'div', 'mp-quiz__processing-fill' );
			bar.appendChild( fill );
			wrap.appendChild( bar );
			var pct = el( 'div', 'mp-quiz__processing-pct', '0%' );
			wrap.appendChild( pct );
			container.appendChild( wrap );

			var durationMs = Math.max( 1, quiz.processing.duration_seconds || 4 ) * 1000;
			var start = Date.now();

			activeInterval = setInterval( function () {
				var frac = Math.min( 1, ( Date.now() - start ) / durationMs );
				fill.style.width = Math.round( frac * 100 ) + '%';
				pct.textContent = Math.round( frac * 100 ) + '%';
				if ( frac >= 1 ) {
					clearActiveInterval();
					submitQuiz();
				}
			}, 100 );
		}

		function submitQuiz() {
			clearActiveInterval();
			container.innerHTML = '<div class="mp-quiz__loading">Calculating your results…</div>';
			api( '/submit', {
				quiz_id: quizId,
				answers: state.answers,
				name: state.name,
				email: state.email,
				lead_id: state.leadId,
				partner_key: partnerKey,
			} ).then( handleResult ).catch( function () {
				container.innerHTML = '<p>Something went wrong submitting your quiz. Please try again.</p>';
			} );
		}

		/* ---------------- Stage 4: Preview / Email Capture / Checkout / Report ---------------- */

		function handleResult( result ) {
			clearActiveInterval();

			if ( ! result || result.code ) {
				container.innerHTML = '';
				container.appendChild( el( 'p', '', 'Something went wrong. Please try again.' ) );
				return;
			}

			state.lastResult = result;
			if ( result.name && ! state.name ) {
				state.name = result.name;
			}

			if ( result.is_premium && ! result.unlocked ) {
				if ( quiz.preview && quiz.preview.enabled ) {
					renderPreview( result );
				} else {
					renderEmailCaptureOrCheckout( result );
				}
			} else {
				renderReport( result );
			}
		}

		function renderPreview( result ) {
			clearActiveInterval();
			var cfg = quiz.preview;
			container.innerHTML = '';
			var wrap = el( 'div', 'mp-quiz__preview' );

			if ( cfg.social_proof_enabled && cfg.social_proof_items && cfg.social_proof_items.length ) {
				var notice = el( 'div', 'mp-quiz__social-proof' );
				wrap.appendChild( notice );
				var idx = 0;
				function updateNotice() {
					var item = cfg.social_proof_items[ idx % cfg.social_proof_items.length ];
					notice.innerHTML = ( item.flag ? escapeHtml( item.flag ) + ' ' : '' ) + '<strong>' + escapeHtml( item.name ) + '</strong> unlocked ' + escapeHtml( item.detail );
					idx++;
				}
				updateNotice();
				activeInterval = setInterval( updateNotice, 2600 );
			}

			wrap.appendChild( el( 'h2', '', escapeHtml( cfg.headline || 'Your result is ready' ) ) );
			if ( cfg.subheadline ) {
				wrap.appendChild( el( 'p', 'mp-quiz__preview-sub', escapeHtml( cfg.subheadline ) ) );
			}

			var lockedBox = el( 'div', 'mp-quiz__preview-locked' );
			lockedBox.appendChild( el( 'p', 'mp-quiz__preview-locked-label', escapeHtml( cfg.locked_label || 'Your Score' ) ) );
			lockedBox.appendChild( el( 'div', 'mp-quiz__preview-locked-icon', '🔒' ) );
			wrap.appendChild( lockedBox );

			var benefits = ( cfg.benefits || [] ).filter( Boolean );
			if ( benefits.length ) {
				var list = el( 'ul', 'mp-quiz__benefits' );
				benefits.forEach( function ( b ) {
					list.appendChild( el( 'li', '', escapeHtml( b ) ) );
				} );
				wrap.appendChild( list );
			}

			var button = el( 'button', 'mp-btn mp-btn--primary', escapeHtml( cfg.button_text || 'Unlock My Results' ) );
			button.type = 'button';
			button.addEventListener( 'click', function () {
				renderEmailCaptureOrCheckout( result );
			} );
			wrap.appendChild( button );

			if ( cfg.guarantee_title || cfg.guarantee_text ) {
				var guarantee = el( 'div', 'mp-quiz__guarantee' );
				if ( cfg.guarantee_title ) guarantee.appendChild( el( 'p', 'mp-quiz__guarantee-title', escapeHtml( cfg.guarantee_title ) ) );
				if ( cfg.guarantee_text ) guarantee.appendChild( el( 'p', '', escapeHtml( cfg.guarantee_text ) ) );
				wrap.appendChild( guarantee );
			}

			var testimonials = ( cfg.testimonials || [] ).filter( function ( t ) { return t && t.text; } );
			if ( testimonials.length ) {
				var tWrap = el( 'div', 'mp-quiz__testimonials' );
				testimonials.forEach( function ( t ) {
					var card = el( 'div', 'mp-quiz__testimonial' );
					card.appendChild( el( 'p', '', escapeHtml( t.text ) ) );
					if ( t.name ) card.appendChild( el( 'p', 'mp-quiz__testimonial-name', escapeHtml( t.name ) ) );
					tWrap.appendChild( card );
				} );
				wrap.appendChild( tWrap );
			}

			container.appendChild( wrap );
			notifyParentHeight();
		}

		function renderEmailCaptureOrCheckout( result ) {
			if ( ! state.email && quiz.email_capture && quiz.email_capture.enabled ) {
				renderStructuredEmailCapture( function () {
					renderCheckout( result );
				} );
			} else {
				renderCheckout( result );
			}
		}

		function renderCheckout( result ) {
			clearActiveInterval();
			var cfg = quiz.checkout || {};
			container.innerHTML = '';
			var wrap = el( 'div', 'mp-quiz__checkout' );

			wrap.appendChild( el( 'h2', '', escapeHtml( cfg.headline || 'Unlock your full personalized report' ) ) );

			var benefits = ( cfg.benefits || [] ).filter( Boolean );
			if ( benefits.length ) {
				var list = el( 'ul', 'mp-quiz__benefits' );
				benefits.forEach( function ( b ) {
					list.appendChild( el( 'li', '', escapeHtml( b ) ) );
				} );
				wrap.appendChild( list );
			}

			if ( result.checkout && result.checkout.checkout_url ) {
				var payBtn = el( 'a', 'mp-btn mp-btn--primary', 'Unlock Full Results' );
				payBtn.href = result.checkout.checkout_url;
				wrap.appendChild( payBtn );
			} else if ( result.checkout && result.checkout.error ) {
				wrap.appendChild( el( 'p', 'mp-quiz__error', escapeHtml( result.checkout.error ) ) );
			}

			if ( cfg.price_caption ) {
				wrap.appendChild( el( 'p', 'mp-quiz__checkout-caption', escapeHtml( cfg.price_caption ) ) );
			}

			container.appendChild( wrap );
			notifyParentHeight();
		}

		/* ---------------- Stage 5: Report ---------------- */

		function renderReport( result ) {
			clearActiveInterval();
			container.innerHTML = '';
			var wrap = el( 'div', 'mp-quiz__result' );
			var band = result.band || {};
			var cfg = quiz.report || {};

			if ( cfg.hero_title || cfg.hero_subtitle ) {
				if ( cfg.hero_title ) wrap.appendChild( el( 'h1', '', escapeHtml( cfg.hero_title ) ) );
				if ( cfg.hero_subtitle ) wrap.appendChild( el( 'p', 'mp-quiz__hero-subtitle', escapeHtml( cfg.hero_subtitle ) ) );
			}

			if ( band.image ) {
				var img = el( 'img', 'mp-quiz__result-image' );
				img.src = band.image;
				wrap.appendChild( img );
			}

			wrap.appendChild( el( 'h2', '', escapeHtml( band.title || 'Your Result' ) ) );
			wrap.appendChild( el( 'p', 'mp-quiz__score', 'Score: ' + result.total_score ) );

			if ( cfg.show_iq_style ) {
				var percent = typeof result.percent === 'number' ? result.percent : estimatePercent( result.total_score );
				var iq = percentToIq( percent );
				var percentile = iqToPercentile( iq );
				var cls = classificationFor( iq, cfg.classification_labels );

				var iqBox = el( 'div', 'mp-quiz__iq-box' );
				iqBox.appendChild( el( 'div', 'mp-quiz__iq-number', String( iq ) ) );
				iqBox.appendChild( el( 'div', 'mp-quiz__iq-label', 'IQ score' ) );
				if ( cls ) iqBox.appendChild( el( 'div', 'mp-quiz__iq-classification', escapeHtml( cls ) ) );
				iqBox.appendChild( el( 'p', 'mp-quiz__iq-percentile', 'Higher than ' + percentile + '% of test takers' ) );
				wrap.appendChild( iqBox );
			}

			wrap.appendChild( el( 'div', 'mp-quiz__result-desc', escapeHtml( band.description || '' ).replace( /\n/g, '<br/>' ) ) );

			if ( band.cta_text && band.cta_url ) {
				var cta = el( 'a', 'mp-btn mp-btn--primary', escapeHtml( band.cta_text ) );
				cta.href = band.cta_url;
				wrap.appendChild( cta );
			}

			if ( cfg.certificate_enabled ) {
				wrap.appendChild( renderCertificate( result, cfg ) );
			}

			if ( cfg.disclaimer ) {
				wrap.appendChild( el( 'p', 'mp-quiz__disclaimer', escapeHtml( cfg.disclaimer ) ) );
			}

			container.appendChild( wrap );
			// Appended as a sibling of `wrap`, not inside it, so the
			// buttons themselves never show up in the captured PDF/image.
			renderDownloadButtons( container, wrap );
			notifyParentHeight();
		}

		function renderDownloadButtons( container, target ) {
			var row = el( 'div', 'mp-quiz__download-row' );

			var pdfBtn = el( 'button', 'mp-btn mp-btn--secondary', 'Download PDF' );
			pdfBtn.type = 'button';
			pdfBtn.addEventListener( 'click', function () {
				downloadReport( 'pdf', target, pdfBtn );
			} );

			var imgBtn = el( 'button', 'mp-btn mp-btn--secondary', 'Download Image' );
			imgBtn.type = 'button';
			imgBtn.addEventListener( 'click', function () {
				downloadReport( 'png', target, imgBtn );
			} );

			row.appendChild( pdfBtn );
			row.appendChild( imgBtn );
			container.appendChild( row );
		}

		/**
		 * Renders `target` to a canvas with html2canvas and either saves it
		 * straight as a PNG or wraps it in an A4-proportioned PDF page via
		 * jsPDF. Both libraries are loaded as script dependencies of this
		 * one (mindpulse-quiz.php); everything happens client-side, no
		 * server rendering involved. An externally-hosted band/logo image
		 * without CORS headers can make html2canvas fail to capture it (or
		 * throw) -- that's a hosting limitation of the image URL, not
		 * something fixable from here.
		 */
		function downloadReport( format, target, triggerBtn ) {
			if ( typeof html2canvas === 'undefined' ) {
				window.alert( 'The download feature could not load. Please check your connection and try again.' );
				return;
			}

			var originalLabel = triggerBtn.textContent;
			triggerBtn.disabled = true;
			triggerBtn.textContent = 'Preparing…';

			html2canvas( target, { useCORS: true, backgroundColor: '#ffffff', scale: 2 } ).then( function ( canvas ) {
				var filename = 'mindpulse-result-' + Date.now();

				if ( 'png' === format ) {
					var link = document.createElement( 'a' );
					link.download = filename + '.png';
					link.href = canvas.toDataURL( 'image/png' );
					link.click();
				} else {
					var jsPDFCtor = ( window.jspdf && window.jspdf.jsPDF ) || window.jsPDF;
					if ( ! jsPDFCtor ) {
						throw new Error( 'jsPDF not loaded' );
					}
					var pdfWidth = 210; // A4 width in mm
					var pdfHeight = ( canvas.height * pdfWidth ) / canvas.width;
					var pdf = new jsPDFCtor( { unit: 'mm', format: [ pdfWidth, pdfHeight ] } );
					// JPEG at 0.9 keeps the PDF a few hundred KB instead of
					// several MB -- a lossless PNG embed is massive overkill
					// for a result page that's mostly flat color and text.
					pdf.addImage( canvas.toDataURL( 'image/jpeg', 0.9 ), 'JPEG', 0, 0, pdfWidth, pdfHeight );
					pdf.save( filename + '.pdf' );
				}
			} ).catch( function ( err ) {
				window.alert( 'Sorry, we couldn\'t generate your download. Please try again, or take a screenshot instead.' );
			} ).finally( function () {
				triggerBtn.disabled = false;
				triggerBtn.textContent = originalLabel;
			} );
		}

		function renderCertificate( result, cfg ) {
			var box = el( 'div', 'mp-quiz__certificate' );
			box.appendChild( el( 'p', 'mp-quiz__certificate-title', escapeHtml( cfg.certificate_title || 'Certificate of Achievement' ) ) );
			box.appendChild( el( 'p', 'mp-quiz__certificate-name', escapeHtml( state.name || 'You' ) ) );
			box.appendChild( el( 'p', 'mp-quiz__certificate-score', 'Score: ' + result.total_score ) );
			box.appendChild( el( 'p', 'mp-quiz__certificate-date', new Date().toLocaleDateString() ) );
			return box;
		}

		/**
		 * Points-mode fallback for the IQ-style percent: this quiz's score
		 * relative to the maximum achievable total (sum of each question's
		 * highest-point option). Correct-mode already returns a real
		 * result.percent from the server.
		 */
		function estimatePercent( totalScore ) {
			var maxPossible = 0;
			( quiz.questions || [] ).forEach( function ( q ) {
				var maxPoints = 0;
				( q.options || [] ).forEach( function ( o ) {
					maxPoints = Math.max( maxPoints, o.points || 0 );
				} );
				maxPossible += maxPoints;
			} );
			if ( maxPossible <= 0 ) {
				return 50;
			}
			return Math.max( 0, Math.min( 100, ( totalScore / maxPossible ) * 100 ) );
		}

		function notifyParentHeight() {
			if ( window.parent !== window ) {
				window.parent.postMessage( { mindpulseHeight: document.body.scrollHeight }, '*' );
			}
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.mp-quiz' ).forEach( initQuiz );
	} );
} )();
