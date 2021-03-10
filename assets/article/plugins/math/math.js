// Icon
ArticleEditor.iconMath = '<svg height="16" viewBox="0 0 16 16" width="16" xmlns="http://www.w3.org/2000/svg"><path d="m11 2c.5522847 0 1 .44771525 1 1 0 .51283584-.3860402.93550716-.8833789.99327227l-.1166211.00672773h-3.92l2.70086881 3.37530495c.26561359.33201696.28976029.79108692.07244006 1.14620443l-.07244006.10318567-2.70186881 3.37530495h3.921c.5128358 0 .9355072.3860402.9932723.8833789l.0067277.1166211c0 .5128358-.3860402.9355072-.8833789.9932723l-.1166211.0067277h-6c-.80039229 0-1.26153081-.8837601-.84621928-1.5335763l.06535047-.0911187 3.49986881-4.375305-3.49986881-4.37530495c-.5000011-.62500138-.09797137-1.53717061.66889291-1.61880377l.1119759-.00589128z"/></svg>';

// Block
ArticleEditor.add('block', 'block.math', {
    mixins: ['block'],
    type: 'math',
    inline: true,
    editable: false,
    control: false,
    toolbar: {
        add: { command: 'addbar.popup', title: '## buttons.add ##' },
        tags: { command: 'math.edit', title: '## math.math ##', icon: ArticleEditor.iconMath }
    },
    create: function() {
        return this.dom('<span>').addClass('math');
    }
});

// Plugin
ArticleEditor.add('plugin', 'math', {
    translations: {
        en: {
            "math": {
                "math": "Math",
                "label": "Type an expression",
                "add": "Add",
                "save": "Save",
                "cancel": "Cancel"
            },
             "blocks": {
                 "math": "Math"
             }
        }
    },
    defaults: {
        classname: 'math',
        mathjax: false,
        delimiters: [
            { left: "$$", right: "$$", display: true },
            //{ left: "$", right: "$", display: false },
            { left: "\\(", right: "\\)", display: false },
            { left: "\\[", right: "\\]", display: true }
        ]
    },
    popups: {
        add: {
            title: '## math.math ##',
            width: '100%',
            form: {
                text: { label: '## math.label ##', type: 'textarea', rows: 6 }
            },
            footer: {
                insert: { title: '## math.add ##', command: 'math.insert', type: 'primary' },
                cancel: { title: '## math.cancel ##', command: 'popup.close' }
            }
        },
        edit: {
            title: '## math.math ##',
            width: '100%',
            form: {
                text: { label: '## math.label ##', type: 'textarea', rows: 6 }
            },
            footer: {
                save: { title: '## math.save ##', command: 'math.save', type: 'primary' },
                cancel: { title: '## math.cancel ##', command: 'popup.close' }
            }
        }
    },
    subscribe: {
        'editor.parse': function(event) {
            this._parse(event);
        },
        'editor.unparse, editor.before.cut, editor.before.copy': function(event) {
            this._unparse(event);
        },
        'editor.ready, source.close, state.undo, state.redo, editor.paste': function() {
            this._render();
        }
    },
    start: function() {
        this.app.addbar.add('math', {
            title: '## blocks.math ##',
            icon: ArticleEditor.iconMath,
            command: 'math.popup'
        });
    },
    popup: function() {
        var stack = this.app.popup.add('math', this.popups.add);
        stack.open({ focus: 'text' });
    },
    edit: function(params, button) {
        var stack = this.app.popup.create('math', this.popups.edit);

        // data
        var instance = this.app.block.get();
        var $block = instance.getBlock();
        var code = decodeURI($block.attr('data-math-code'));
        code = this._decodeSigns(code);

        // set
        stack.setData({ text: code });

        // open
        this.app.popup.open({ button: button, focus: 'text' });
    },
    save: function(stack) {
        this.app.popup.close();

        var current = this.app.block.get();
        var $block = current.getBlock();
        var code = this._buildBlock($block, stack);

        if (code !== false) {
            this._renderDisplay($block);
            this._renderTypeset($block, code);
        }
    },
    insert: function(stack) {
        this.app.popup.close();

        // create
        var instance = this.app.create('block.math');
        var $block = instance.getBlock();
        var code = this._buildBlock($block, stack);

        if (code !== false) {
            this.app.block.add({ instance: instance });

            this._renderDisplay($block);
            this._renderTypeset($block, code);
        }
    },

    // private
    _buildBlock: function($block, stack) {
        var data = stack.getData();
        var code = data.text.trim();

        var delim = this._parseDelim(code);
        if (!delim) {
            return false;
        }

        $block.attr('data-math-display', delim.display);
        $block.attr('data-math-code', encodeURI(code));

        // katex
        if (!this.opts.math.mathjax) {
            code = code.replace(delim.left, '').replace(delim.right, '');
        }

        $block.html(code);

        return code;
    },
    _renderTypeset: function($block, code) {
        if (this.opts.math.mathjax) {
            // mathjax
            this._getMathJax().typeset();
        }
        else {
            // katex
            this._renderNode($block, code);
        }
    },
    _renderDisplay: function($node) {
        var display = $node.attr('data-math-display');
        var obj = { display: 'inline-block', 'text-align': '' };
        if (display) {
            obj = { display: 'block', 'text-align': 'center' };
            $node.addClass('math-display');
        }
        else {
            $node.removeClass('math-display');
        }

        $node.css(obj);
    },
    _renderNode: function($node, code) {
        this._getKatex().render(code, $node.get(), { displayMode: $node.attr('data-math-display') });
    },
    _render: function() {
        // mathjax
        if (this.opts.math.mathjax) {
            this._getMathJax().typeset();
        }

        this.app.editor.getBody().find('.' + this.opts.math.classname).each(function($node) {
            this._renderDisplay($node);

            // katex
            if (!this.opts.math.mathjax) {
                if ($node.attr('data-math-render')) return;
                this._renderNode($node, $node.text());
                $node.attr('data-math-render', true);
            }
        }.bind(this));
    },
    _parseDelim: function(code) {
        var delim = this.opts.math.delimiters;
        for (var i = 0; i < delim.length; i++) {
            var re = new RegExp('^' + this.app.utils.escapeRegExp(delim[i].left));
            if (code.search(re) !== -1) {
                return delim[i];
            }
        }
    },
    _getMathJax: function() {
        return this.app.editor.getWinNode().MathJax;
    },
    _getKatex: function() {
        return this.app.editor.getWinNode().katex;
    },
    _getReplacer: function(code, datacode, display) {
        var start = '<span class=' + this.opts.math.classname + ' data-' + this.prefix + '-type="math" data-math-code="' + datacode + '" data-math-display="' + display + '">';
        var end = '</span>';

        return start + code + end;
    },
    _encodeSigns: function(code) {
        code = code.replace(/\$\$/g, 'xdoubledollarsignz');
        code = code.replace(/\$/g, 'xsingledollarsignz');

        return code;
    },
    _decodeSigns: function(code) {
        code = code.replace(/xdoubledollarsignz/g, "$$$");
        code = code.replace(/xsingledollarsignz/g, "$");

        return code;
    },
    _parse: function(event) {
        var html = event.get('html');
        var delim = this.opts.math.delimiters;

        for (var i = 0; i < delim.length; i++) {
            var rcont = (delim[i].display) ? '([\\w\\W]*?)' : '(.*?)';
            var re = new RegExp(this.app.utils.escapeRegExp(delim[i].left) + rcont + this.app.utils.escapeRegExp(delim[i].right), 'g');
            var match = html.match(re);
            if (match != null) {
                for (var z = 0; z < match.length; z++) {
                    // code
                    var code = match[z].trim();
                    var datacode = this._encodeSigns(code)
                    datacode = encodeURI(datacode);

                    // katex
                    if (!this.opts.math.mathjax) {
                        code = code.replace(delim[i].left, '').replace(delim[i].right, '');
                    }

                    // replace
                    html = html.replace(match[z], this._getReplacer(code, datacode, delim[i].display));
                }
            }
        }

        event.set('html', html);
    },
    _unparse: function(event) {
        var html = event.get('html');

        html = this.app.utils.wrap(html, function($w) {
            $w.find('.' + this.opts.math.classname).each(function($node) {
                var code = decodeURI($node.attr('data-math-code'));
                code = this._decodeSigns(code);
                code = code.replace(/&/g, 'xampsignmathz');
                $node.text(code);
                $node.unwrap();
            }.bind(this));
        }.bind(this));

        html = html.replace(/xampsignmathz/g, '&');

        event.set('html', html);
    },
    _copy: function(event) {
        var html = event.get('html');

        event.set('html', html);
    }
});