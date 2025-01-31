(function() {
    // Add Communico Button
    tinymce.PluginManager.add( 'communico_button', function( editor ) {
        editor.addButton( 'communico_button', {
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
                        {type: 'listbox',
                         name: 'ages',
                         label: 'Ages',
                         values: [
                            {text: 'None', value: ''},
                            {text: 'Young Children (0-5)', value: 'Young%20Children%20%280-5%29%0D%0A'},
                            {text: 'Kids (6-10)', value: 'Kids%20%286-10%29'},
                            {text: 'Teens (11-17)', value: 'Teens%20%2811-17%29'},
                            {text: 'Adults (18+)', value: 'Adults%20%2818%2B%29'},
                            {text: 'All Ages', value: 'All%20Ages'}
                        ]},
                        {type: 'listbox',
                         name: 'types',
                         label: 'Types',
                         values: [
                            {text: 'None', value: ''},
                            {text: 'Arts/Crafts/Hobbies', value: 'Art%2FCrafts%2FHobbies'},
                            {text: 'Book Groups', value: 'Book%20Groups'},
                            {text: 'Coding', value: 'Coding'},
                            {text: 'Computers/Technology', value: 'Computers%2FTechnology'},
                            {text: 'Cooking/Food/Gardening', value: 'Cooking%2FFood%2FGardening'},
                            {text: 'Creative Writing and Poetry', value: 'Creative%20Writing%20and%20Poetry'},
                            {text: 'Games/Activities', value: 'Games%2FActivities'},
                            {text: 'History/Travel/Genealogy', value: 'History%2FTravel%2FGenealogy'},
                            {text: 'Home School', value: 'Home%20School'},
                            {text: 'Homework Help', value: 'Homework%20Help'},
                            {text: 'Movies', value: 'Movies'},
                            {text: 'Performances/Special Events', value: 'Performances%2FSpecial%20Events'},
                            {text: 'Personal Finance', value: 'Personal%20Finance'},
                            {text: 'Reading Support', value: 'Reading%20Support'},
                            {text: 'Science/Animals', value: 'Science%2FAnimals'},
                            {text: 'Small Business and Nonprofit', value: 'Small%20Business%20and%20Nonprofit'},
                            {text: 'Storytime', value: 'Storytime'},
                            {text: 'Wellness', value: 'Wellness'}
                        ]},
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
                    ],
                    onsubmit: function(e) {

                var formatstyle = e.data.formatstyle;
                var locationid = e.data.locationid;
                var ages = e.data.ages;
                var types = e.data.types;
                var term = e.data.term;
                var removeText = e.data.removeText;
                var daysahead = e.data.daysahead;
                
                editor.insertContent('[communico formatstyle="'+ formatstyle +'" locationid="' + locationid + '" ages="' + ages + '" types="' + types + '" term="' + term + '" removeText="' + removeText + '" daysahead="' + daysahead + '"]');

                     }
                });
            }
        });
    }); // **Closing parenthesis added here**
})();