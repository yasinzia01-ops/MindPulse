/**
 * Admin quiz builder: renders the Questions and Score Bands editors
 * from the seeded JSON, lets the admin add/remove/edit them, and
 * serializes the current state into #mp-quiz-data-input right before
 * the form submits.
 */
( function () {
	'use strict';

	var seedEl = document.getElementById( 'mp-quiz-data-seed' );
	var seed = { questions: [], bands: [], settings: {} };
	try {
		seed = JSON.parse( seedEl.textContent || '{}' );
	} catch ( e ) {}

	var questionsWrap = document.getElementById( 'mp-questions' );
	var bandsWrap = document.getElementById( 'mp-bands' );

	var qCounter = 0;
	var bCounter = 0;

	function uid( prefix ) {
		return prefix + '_' + Date.now().toString( 36 ) + '_' + Math.floor( Math.random() * 1000 );
	}

	function el( tag, cls, html ) {
		var node = document.createElement( tag );
		if ( cls ) node.className = cls;
		if ( html !== undefined ) node.innerHTML = html;
		return node;
	}

	/* ---------------- Questions ---------------- */

	function addQuestion( question ) {
		question = question || { id: uid( 'q' ), text: '', options: [ { label: '', points: 0 } ] };
		qCounter++;

		var card = el( 'div', 'mp-card mp-question' );
		card.dataset.qid = question.id;

		card.appendChild( el( 'div', 'mp-card__header', '<strong>' + 'Question ' + qCounter + '</strong>' ) );

		var textInput = el( 'input' );
		textInput.type = 'text';
		textInput.className = 'large-text mp-q-text';
		textInput.placeholder = 'Question text';
		textInput.value = question.text || '';
		card.appendChild( textInput );

		var optionsWrap = el( 'div', 'mp-options' );
		card.appendChild( optionsWrap );

		( question.options || [] ).forEach( function ( opt ) {
			optionsWrap.appendChild( buildOptionRow( opt ) );
		} );

		var addOptBtn = el( 'button', 'button button-small', '+ Add Option' );
		addOptBtn.type = 'button';
		addOptBtn.addEventListener( 'click', function () {
			optionsWrap.appendChild( buildOptionRow( { label: '', points: 0 } ) );
		} );
		card.appendChild( addOptBtn );

		var removeBtn = el( 'button', 'button-link mp-remove', 'Remove question' );
		removeBtn.type = 'button';
		removeBtn.addEventListener( 'click', function () {
			card.remove();
		} );
		card.appendChild( removeBtn );

		questionsWrap.appendChild( card );
	}

	function buildOptionRow( opt ) {
		var row = el( 'div', 'mp-option-row' );

		var label = el( 'input' );
		label.type = 'text';
		label.className = 'regular-text mp-opt-label';
		label.placeholder = 'Answer label';
		label.value = opt.label || '';

		var points = el( 'input' );
		points.type = 'number';
		points.className = 'small-text mp-opt-points';
		points.placeholder = 'Points';
		points.value = opt.points || 0;

		var remove = el( 'button', 'button-link mp-remove', '×' );
		remove.type = 'button';
		remove.addEventListener( 'click', function () {
			row.remove();
		} );

		row.appendChild( label );
		row.appendChild( points );
		row.appendChild( remove );

		return row;
	}

	/* ---------------- Score Bands ---------------- */

	function addBand( band ) {
		band = band || { key: uid( 'band' ), min: 0, max: 0, title: '', description: '', image: '', cta_text: '', cta_url: '' };
		bCounter++;

		var card = el( 'div', 'mp-card mp-band' );
		card.dataset.key = band.key;

		card.appendChild( el( 'div', 'mp-card__header', '<strong>' + 'Band ' + bCounter + '</strong>' ) );

		card.appendChild( labeledInput( 'Min score', 'number', 'mp-band-min', band.min ) );
		card.appendChild( labeledInput( 'Max score', 'number', 'mp-band-max', band.max ) );
		card.appendChild( labeledInput( 'Result title', 'text', 'mp-band-title', band.title ) );
		card.appendChild( labeledTextarea( 'Result description', 'mp-band-desc', band.description ) );
		card.appendChild( labeledInput( 'Image URL', 'text', 'mp-band-image', band.image ) );
		card.appendChild( labeledInput( 'CTA text', 'text', 'mp-band-cta-text', band.cta_text ) );
		card.appendChild( labeledInput( 'CTA URL', 'text', 'mp-band-cta-url', band.cta_url ) );

		var removeBtn = el( 'button', 'button-link mp-remove', 'Remove band' );
		removeBtn.type = 'button';
		removeBtn.addEventListener( 'click', function () {
			card.remove();
		} );
		card.appendChild( removeBtn );

		bandsWrap.appendChild( card );
	}

	function labeledInput( labelText, type, cls, value ) {
		var wrap = el( 'p' );
		var label = el( 'label', '', labelText + '<br/>' );
		var input = document.createElement( 'input' );
		input.type = type;
		input.className = cls + ' regular-text';
		input.value = value || ( type === 'number' ? 0 : '' );
		label.appendChild( input );
		wrap.appendChild( label );
		return wrap;
	}

	function labeledTextarea( labelText, cls, value ) {
		var wrap = el( 'p' );
		var label = el( 'label', '', labelText + '<br/>' );
		var textarea = document.createElement( 'textarea' );
		textarea.className = cls + ' large-text';
		textarea.rows = 2;
		textarea.value = value || '';
		label.appendChild( textarea );
		wrap.appendChild( label );
		return wrap;
	}

	/* ---------------- Serialize on submit ---------------- */

	function serialize() {
		var questions = Array.prototype.map.call( questionsWrap.querySelectorAll( '.mp-question' ), function ( card ) {
			var options = Array.prototype.map.call( card.querySelectorAll( '.mp-option-row' ), function ( row ) {
				return {
					label: row.querySelector( '.mp-opt-label' ).value,
					points: parseInt( row.querySelector( '.mp-opt-points' ).value, 10 ) || 0,
				};
			} );

			return {
				id: card.dataset.qid,
				text: card.querySelector( '.mp-q-text' ).value,
				options: options,
			};
		} );

		var bands = Array.prototype.map.call( bandsWrap.querySelectorAll( '.mp-band' ), function ( card ) {
			return {
				key: card.dataset.key,
				min: parseInt( card.querySelector( '.mp-band-min' ).value, 10 ) || 0,
				max: parseInt( card.querySelector( '.mp-band-max' ).value, 10 ) || 0,
				title: card.querySelector( '.mp-band-title' ).value,
				description: card.querySelector( '.mp-band-desc' ).value,
				image: card.querySelector( '.mp-band-image' ).value,
				cta_text: card.querySelector( '.mp-band-cta-text' ).value,
				cta_url: card.querySelector( '.mp-band-cta-url' ).value,
			};
		} );

		var settings = {
			lead_capture_before: !! document.querySelector( '[name="settings_lead_capture_before"]' ).checked,
			is_premium: !! document.getElementById( 'settings_is_premium' ).checked,
			price: parseFloat( document.querySelector( '[name="settings_price"]' ).value ) || 0,
			currency: document.querySelector( '[name="settings_currency"]' ).value || 'usd',
			thank_you_message: document.getElementById( 'settings_thank_you' ).value,
		};

		return { questions: questions, bands: bands, settings: settings };
	}

	var form = document.getElementById( 'mp-quiz-form' );
	form.addEventListener( 'submit', function () {
		document.getElementById( 'mp-quiz-data-input' ).value = JSON.stringify( serialize() );
	} );

	document.getElementById( 'mp-add-question' ).addEventListener( 'click', function () {
		addQuestion();
	} );
	document.getElementById( 'mp-add-band' ).addEventListener( 'click', function () {
		addBand();
	} );

	/* ---------------- Init from seed ---------------- */

	( seed.questions || [] ).forEach( addQuestion );
	( seed.bands || [] ).forEach( addBand );

	if ( ! seed.questions || ! seed.questions.length ) {
		addQuestion();
	}
	if ( ! seed.bands || ! seed.bands.length ) {
		addBand();
	}
} )();
