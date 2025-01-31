(function ($) {
    'use strict';

    // Ajax function to get data from Communico API
    function getCommunicoData(data) {
        return $.ajax({
            url: MyCommunicoDataPuller.ajax_url,
            type: 'post',
            data: {
                action: 'get_communico_data',
                data: data
            }
        });
    }

    var data = getParameters();
    getCommunicoData(data).done(function (response) {
        // Parse the response
        var responseData = JSON.parse(response);

        // Create a container element to hold the event data
        var container = $('<div>');

        // Iterate over each event and create HTML elements for each piece of data
        responseData.events.forEach(function(event) {
            var image = event.featureImage ? event.featureImage : event.eventImage;
            var title = event.title;
            var subTitle = event.subTitle;
            var registrationUrl = event.eventRegistrationUrl;

            // Create HTML elements for each piece of data
            var eventImage = $('<img>').attr('src', image).attr('alt', 'Event image');
            var eventTitle = $('<h1>').text(title);
            var eventSubTitle = $('<h2>').text(subTitle);
            var eventRegistration = $('<a>').attr('href', registrationUrl).text('Register');

            // Append the HTML elements to the container
            container.append(eventImage);
            container.append(eventTitle);
            container.append(eventSubTitle);
            container.append(eventRegistration);
        });

        // Empty the previous event data and append the new container to the body
        $('#communico-results').empty().append(container);
    });

    // Get the parameters from the HTML elements
    function getParameters() {
        return {
            formatstyle: $('#formatstyle').val(),
            locationId: $('#locationId').val(),
            ages: $('#ages').val(),
            types: $('#types').val(),
            term: $('#term').val(),
            removeText: $('#removeText').val(), // Added removeText parameter
            daysahead: $('#daysahead').val() // Added daysahead parameter
        };
    }

    // Event to handle form submit
    $('#communico-form').on('submit', function (event) {
        event.preventDefault();

        var data = getParameters();

        getCommunicoData(data).done(function (response) {
            // Handle the response here
            console.log(response);
        });
    });

}(jQuery));
