$(function(){
	$.timepicker.regional['fr_FR'] = {
		currentText: 'Maintenant',
		closeText: 'terminé',
		amNames: ['AM', 'A'],
		pmNames: ['PM', 'P'],
		timeFormat: 'HH:mm:ss',
		timeSuffix: '',
		timeOnlyTitle: 'Choisir le temps',
		timeText: 'Temps',
		hourText: 'Heure',
		minuteText: 'Minute',
		secondText: 'Seconde',
		millisecText: 'Millisecond',
		timezoneText: 'Time Zone',
		isRTL: false
	};
	$.timepicker.setDefaults($.timepicker.regional['fr_FR']);
});