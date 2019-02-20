import 'typeahead.js';
import Bloodhound from "bloodhound-js";

$(document).ready(function() {
    var posts = new Bloodhound({
        datumTokenizer: function(datum){
            var nameTokens = Bloodhound.tokenizers.whitespace(datum['name']);
            var idTokens = Bloodhound.tokenizers.whitespace(datum['id']);
            var postCodeTokens = Bloodhound.tokenizers.whitespace(datum['code']);
            return nameTokens.concat(idTokens).concat(postCodeTokens);
        },
        queryTokenizer: Bloodhound.tokenizers.whitespace,
        prefetch: {
            url: "../api/post/list",
            filter: function(response) {      
                return response[0]['data']['posts'];
            }
        }
    });

    posts.initialize();

    $('#bloodhound .typeahead').typeahead({
    hint: true,
    highlight: true,
    minLength: 1
    },
    {
    displayKey: 'name',
    source: posts.ttAdapter(),  
    });  
});