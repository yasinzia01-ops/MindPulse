/**
 * Frontend quiz runner used by both the [mindpulse_quiz] shortcode and
 * the Elementor widget. Renders one question at a time, captures the
 * lead's email as soon as they provide it (for abandon-email recovery),
 * and on completion posts to /submit and shows the result profile
 * (with a Stripe checkout redirect if the quiz is premium).
 */
( function () {
	'use strict';

	function qs( root, sel ) {
		return root.querySelector( sel );
	}

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
			step: 0, // 0 = lead capture (if enabled), 1..n = questions, n+1 = result
			answers: {},
			leadId: 0,
			name: '',
			email: '',
		};

		var leadCaptureFirst = !! ( quiz.settings && quiz.settings.lead_capture_before );

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
				renderResult( res );
			} ).catch( function () {
				maybeResume();
			} );
		}

		function maybeResume() {
			var resumeToken = getUrlParam( 'mp_resume' );
			var resumeQuizId = getUrlParam( 'mp_quiz' );

			if ( ! resumeToken || String( resumeQuizId ) !== String( quizId ) ) {
				render();
				return;
			}

			container.innerHTML = '<div class="mp-quiz__loading">Resuming your quiz…</div>';

			apiGet( '/resume', { token: resumeToken, quiz_id: quizId } ).then( function ( res ) {
				if ( ! res || res.code ) {
					render();
					return;
				}

				state.leadId = res.lead_id;
				state.name = res.name || '';
				state.email = res.email || '';
				state.answers = res.answers || {};
				state.step = res.last_step || 0;

				render();
			} ).catch( function () {
				render();
			} );
		}

		function render() {
			container.innerHTML = '';

			if ( leadCaptureFirst && state.step === 0 ) {
				renderLeadCapture();
				return;
			}

			var questionIndex = state.step - ( leadCaptureFirst ? 1 : 0 );

			if ( questionIndex < quiz.questions.length ) {
				renderQuestion( quiz.questions[ questionIndex ], questionIndex );
				return;
			}

			if ( ! leadCaptureFirst && ! state.email ) {
				renderLeadCapture();
				return;
			}

			renderSubmitting();
			submitQuiz();
		}

		function renderLeadCapture() {
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
				state.step++;
				render();
			} );

			wrap.appendChild( nameInput );
			wrap.appendChild( emailInput );
			wrap.appendChild( button );
			container.appendChild( wrap );
		}

		function renderQuestion( question, index ) {
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
					render();
				} );
				list.appendChild( btn );
			} );

			wrap.appendChild( list );
			container.appendChild( wrap );
		}

		function renderSubmitting() {
			container.appendChild( el( 'div', 'mp-quiz__loading', 'Calculating your results…' ) );
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
			} ).then( function ( res ) {
				if ( res && res.lead_id ) {
					state.leadId = res.lead_id;
				}
			} ).catch( function () {} );
		}

		function submitQuiz() {
			api( '/submit', {
				quiz_id: quizId,
				answers: state.answers,
				name: state.name,
				email: state.email,
				lead_id: state.leadId,
				partner_key: partnerKey,
			} ).then( renderResult ).catch( function () {
				container.innerHTML = '<p>Something went wrong submitting your quiz. Please try again.</p>';
			} );
		}

		function renderResult( result ) {
			container.innerHTML = '';
			var wrap = el( 'div', 'mp-quiz__result' );

			if ( ! result || result.code ) {
				wrap.appendChild( el( 'p', '', 'Something went wrong. Please try again.' ) );
				container.appendChild( wrap );
				return;
			}

			var band = result.band || {};

			if ( band.image ) {
				wrap.appendChild( el( 'img', 'mp-quiz__result-image', '' ) );
				wrap.querySelector( '.mp-quiz__result-image' ).src = band.image;
			}

			wrap.appendChild( el( 'h2', '', escapeHtml( band.title || 'Your Result' ) ) );
			wrap.appendChild( el( 'p', 'mp-quiz__score', 'Score: ' + result.total_score ) );

			if ( result.is_premium && ! result.unlocked ) {
				wrap.appendChild( el( 'p', '', 'Unlock your full personalized report:' ) );

				if ( result.checkout && result.checkout.checkout_url ) {
					var payBtn = el( 'a', 'mp-btn mp-btn--primary', 'Unlock Full Results' );
					payBtn.href = result.checkout.checkout_url;
					wrap.appendChild( payBtn );
				} else if ( result.checkout && result.checkout.error ) {
					wrap.appendChild( el( 'p', 'mp-quiz__error', escapeHtml( result.checkout.error ) ) );
				}
			} else {
				wrap.appendChild( el( 'div', 'mp-quiz__result-desc', escapeHtml( band.description || '' ).replace( /\n/g, '<br/>' ) ) );

				if ( band.cta_text && band.cta_url ) {
					var cta = el( 'a', 'mp-btn mp-btn--primary', escapeHtml( band.cta_text ) );
					cta.href = band.cta_url;
					wrap.appendChild( cta );
				}
			}

			container.appendChild( wrap );

			if ( window.parent !== window ) {
				window.parent.postMessage( { mindpulseHeight: document.body.scrollHeight }, '*' );
			}
		}

		function escapeHtml( str ) {
			var div = document.createElement( 'div' );
			div.textContent = str == null ? '' : String( str );
			return div.innerHTML;
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.mp-quiz' ).forEach( initQuiz );
	} );
} )();
