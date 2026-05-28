(function() {
    var ageOptions = [
        {name: 'age_young_children', label: 'Young Children (0-5)', value: 'Young%20Children%20%280-5%29'},
        {name: 'age_kids', label: 'Kids (6-10)', value: 'Kids%20%286-10%29'},
        {name: 'age_teens', label: 'Teens (11-17)', value: 'Teens%20%2811-17%29'},
        {name: 'age_adults', label: 'Adults (18+)', value: 'Adults%20%2818%2B%29'},
        {name: 'age_all_ages', label: 'All Ages', value: 'All%20Ages'}
    ];

    var typeOptions = [
        {name: 'type_arts_crafts_hobbies', label: 'Arts/Crafts/Hobbies', value: 'Art%2FCrafts%2FHobbies'},
        {name: 'type_book_groups', label: 'Book Groups', value: 'Book%20Groups'},
        {name: 'type_coding', label: 'Coding', value: 'Coding'},
        {name: 'type_computers_technology', label: 'Computers/Technology', value: 'Computers%2FTechnology'},
        {name: 'type_cooking_food_gardening', label: 'Cooking/Food/Gardening', value: 'Cooking%2FFood%2FGardening'},
        {name: 'type_creative_writing_poetry', label: 'Creative Writing and Poetry', value: 'Creative%20Writing%20and%20Poetry'},
        {name: 'type_games_activities', label: 'Games/Activities', value: 'Games%2FActivities'},
        {name: 'type_history_travel_genealogy', label: 'History/Travel/Genealogy', value: 'History%2FTravel%2FGenealogy'},
        {name: 'type_home_school', label: 'Home School', value: 'Home%20School'},
        {name: 'type_homework_help', label: 'Homework Help', value: 'Homework%20Help'},
        {name: 'type_movies', label: 'Movies', value: 'Movies'},
        {name: 'type_performances_special_events', label: 'Performances/Special Events', value: 'Performances%2FSpecial%20Events'},
        {name: 'type_personal_finance', label: 'Personal Finance', value: 'Personal%20Finance'},
        {name: 'type_reading_support', label: 'Reading Support', value: 'Reading%20Support'},
        {name: 'type_science_animals', label: 'Science/Animals', value: 'Science%2FAnimals'},
        {name: 'type_small_business_nonprofit', label: 'Small Business and Nonprofit', value: 'Small%20Business%20and%20Nonprofit'},
        {name: 'type_storytime', label: 'Storytime', value: 'Storytime'},
        {name: 'type_wellness', label: 'Wellness', value: 'Wellness'}
    ];

    function buildCheckboxFields(options) {
        return options.map(function(option) {
            return {
                type: 'checkbox',
                name: option.name,
                label: option.label
            };
        });
    }

    function collectSelectedValues(formData, options) {
        return options.filter(function(option) {
            return formData[option.name];
        }).map(function(option) {
            return option.value;
        }).join(',');
    }

    function shortcodeEscape(value) {
        return String(value || '').replace(/"/g, '&quot;');
    }

    // Add Communico Button
    tinymce.PluginManager.add( 'communicoButton', function( editor ) {
        editor.addButton( 'communicoButton', {
            title: 'Communico Button',
            icon: 'communico',
            onclick: function() {
                editor.windowManager.open({
                    title: 'Enter Parameters',
                    body: [
                        {type: 'listbox',
                         name: 'locationid',
                         label: 'Location',
                         values: [
                            {text: 'None', value: ''},
                            {text: 'Birmingham', value: '416'},
                            {text: 'Heatherdowns', value: '417'},
                            {text: 'Holland', value: '418'},
                            {text: 'Kent', value: '419'},
                            {text: 'King Road', value: '420'},
                            {text: 'Lagrange', value: '421'},
                            {text: 'Locke', value: '422'},
                            {text: 'Main Library', value: '415'},
                            {text: 'Maumee', value: '423'},
                            {text: 'Mott', value: '1608'},
                            {text: 'Oregon', value: '425'},
                            {text: 'Point Place', value: '426'},
                            {text: 'Reynolds Corners', value: '427'},
                            {text: 'Sanger', value: '428'},
                            {text: 'South', value: '429'},
                            {text: 'Sylvania', value: '430'},
                            {text: 'Toledo Heights', value: '431'},
                            {text: 'Washington', value: '432'},
                            {text: 'Waterville', value: '433'},
                            {text: 'West Toledo', value: '434'}
                        ]},
                        {type: 'label', text: 'Ages (select one or more)'},
                    ].concat(
                        buildCheckboxFields(ageOptions),
                        [
                            {type: 'label', text: 'Types (select one or more)'}
                        ],
                        buildCheckboxFields(typeOptions),
                        [
                            {type: 'listbox',
                             name: 'daysahead',
                             label: 'Days Ahead',
                             values: [
                                {text: '30', value: '30'},
                                {text: '60', value: '60'},
                                {text: '90', value: '90'},
                                {text: '120', value: '120'},
                                {text: '180', value: '180'},
                                {text: '365', value: '365'}
                            ]},
                            {type: 'textbox', name: 'term', label: 'Term'},
                            {type: 'textbox', name: 'removeText', label: 'Remove Text'}
                        ]
                    ),
                    onsubmit: function(e) {
                        var formatstyle = e.data.formatstyle;
                        var locationid = e.data.locationid;
                        var ages = collectSelectedValues(e.data, ageOptions);
                        var types = collectSelectedValues(e.data, typeOptions);
                        var term = e.data.term;
                        var removeText = e.data.removeText;
                        var daysahead = e.data.daysahead;

                        editor.insertContent('[communico formatstyle="'+ shortcodeEscape(formatstyle) +'" locationid="' + shortcodeEscape(locationid) + '" ages="' + shortcodeEscape(ages) + '" types="' + shortcodeEscape(types) + '" term="' + shortcodeEscape(term) + '" removeText="' + shortcodeEscape(removeText) + '" daysahead="' + shortcodeEscape(daysahead) + '"]');
                    }
                });
            }
        });
    }); // **Closing parenthesis added here**
})();
