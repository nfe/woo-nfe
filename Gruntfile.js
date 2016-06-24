module.exports = function(grunt) {
	grunt.initConfig({
		pkg: grunt.file.readJSON('package.json'),

		makepot: {
			target: {
				options: {
					domainPath: '/languages/',
					mainFile: 'woocommerce-nfe.php',
					potFilename: 'woocommerce-nfe.pot',
					type: 'wp-plugin',
					exclude: [],
					processPot: function( pot, options ) {
						pot.headers['report-msgid-bugs-to'] = 'https://github.com/nfe/woocommerce-nfe';
						pot.headers['plural-forms'] = 'nplurals=2; plural=n != 1;';
						pot.headers['last-translator'] = 'Renato Alves <contato@ralv.es \n';
						pot.headers['language-team'] = 'Renato Alves <contato@ralv.es \n';
						pot.headers['x-poedit-basepath'] = '.\n';
						pot.headers['x-poedit-language'] = 'English\n';
						pot.headers['x-poedit-country'] = '\n';
						pot.headers['x-poedit-sourcecharset'] = 'utf-8\n';
						pot.headers['x-poedit-keywordslist'] = '__;_e;_x;esc_html_e;esc_html__;esc_attr_e;esc_attr__;_ex:1,2c;_nx:4c,1,2;_nx_noop:4c,1,2;_x:1,2c;_n:1,2;_n_noop:1,2;__ngettext_noop:1,2;_c,_nc:4c,1,2;\n';
						pot.headers['x-textdomain-support'] = 'yes\n';
						return pot;
					}
				}
			}
		},
	});
	
	require('load-grunt-tasks')(grunt);
}
