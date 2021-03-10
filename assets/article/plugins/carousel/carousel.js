ArticleEditor.add('plugin', 'carousel', {
    translations: {
        en: {
             "carousel": {
                 "carousel": "Carousel",
                 "save": "Save",
                 "cancel": "Cancel",
                 "insert": "Insert"
             }
        }
    },
    defaults: {
        upload: false,
        select: false,
        name: 'file',
        data: false,
        multiple: true
    },
    popups: {
        add: {
            title: '## carousel.carousel ##',
            width: '100%',
            footer: {
                insert: { title: '## carousel.insert ##', command: 'carousel.insert', type: 'primary' },
                cancel: { title: '## carousel.cancel ##', command: 'popup.close' }
            }
        },
        edit: {
            title: '## carousel.carousel ##',
            width: '100%',
            footer: {
                save: { title: '## carousel.save ##', command: 'carousel.save', type: 'primary' },
                cancel: { title: '## carousel.cancel ##', command: 'popup.close' }
            }
        }
    },
    start: function() {
        this.app.addbar.add('carousel', {
            title: '## carousel.carousel ##',
            icon: '<svg height="16" viewBox="0 0 16 16" width="16" xmlns="http://www.w3.org/2000/svg"><path d="m1 6c.51283584 0 .93550716.38604019.99327227.88337887l.00672773.11662113v6c0 .4964612.44481521.9373059 1.049825.9938787l.13199318.0061213h5.81818182c.55228475 0 1 .4477153 1 1 0 .5128358-.38604019.9355072-.88337887.9932723l-.11662113.0067277h-5.81818182c-1.67882337 0-3.08194674-1.2331302-3.17671765-2.8277989l-.00510053-.1722011v-6c0-.55228475.44771525-1 1-1zm12.6-6c1.3254834 0 2.4 1.0745166 2.4 2.4v8.2c0 .0812991-.0040424.161654-.0119371.2408747.04537.286293-.0350323.5849432-.229131.8106316-.3901386.7980655-1.2102555 1.3484937-2.1589319 1.3484937h-8.2c-.0093721 0-.01873166-.0000537-.02807838-.0001609l-.37192162.0001604c-.26521649 0-.5195704-.1053563-.70710678-.2928927l.05843073.052268c-.4838201-.2353989-.8766931-.6286113-1.11166129-1.1126794-.19291421-.2239005-.27189946-.5180346-.2277519-.8010756-.00769603-.0805757-.01191076-.1626056-.01191076-.2456198v-8.2c0-1.3254834 1.0745166-2.4 2.4-2.4zm-1.596 8.632-.76.937.839 1.431h1.517c.0486011 0 .0951777-.0086678.1382683-.0245418zm-4.582-1.631-2.176 3.83.169.169h4.349l-.436-.744-.04995732-.0453903c-.12825052-.1334488-.2131256-.2958557-.25256531-.46760617zm6.178-5.001h-8.2c-.2209139 0-.4.1790861-.4.4v4.814l1.53963435-2.70800941c.35805102-.6301698 1.23187816-.67285287 1.65793101-.12170559l.07440231.11030062 1.89203233 3.23141438 1.0987033-1.35538405c.384092-.47423473 1.0895396-.491008 1.4981306-.06381668l.0827862.09833145 1.1563799 1.56386928v-5.569c0-.19329966-.1371128-.35457492-.319386-.39187342z"/></svg>',
            command: 'carousel.popup'
        });
    },
    popup: function() {
        // create
        var stack = this.app.popup.add('slideshow', this.popups.add);

        // body
        var $body = stack.getBody();

        // box
        this.$box = this.dom('<div>').addClass(this.prefix + '-slideshow-items');
        $body.append(this.$box);

        // upload
        this.$upload = this.app.image.createUploadBox(this.opts.carousel.upload, $body);

        // select box
        this.app.image.createSelectBox(this.opts.carousel.select, $body, 'carousel.insertFromSelect');

        // open
        stack.open();

        // build upload
        this._buildUpload(this.$upload, 'carousel.insertByUpload');
    },
    edit: function(params, button) {
        // create
        var stack = this.app.popup.create('slideshow', this.popups.edit);

        // body
        var $body = stack.getBody();

        // data
        var current = this.app.block.get();
        var $block = current.getBlock();
        var $elms = $block.find('img');

        // box
        this.$box = this.dom('<div>').addClass(this.prefix + '-slideshow-items');
        $body.append(this.$box);

        // items
        $elms.each(function($node) {
            this._buildBoxItem($node.attr('src'));
        }.bind(this));

        // upload
        this.$upload = this.app.image.createUploadBox(this.opts.carousel.upload, $body);

        // select box
        this.app.image.createSelectBox(this.opts.carousel.select, $body, 'carousel.insertFromSelect');

        // open
        this.app.popup.open({ button: button });

        // build upload
        this._buildUpload(this.$upload, 'carousel.insertByUpload');
    },
    insertByUpload: function(response) {
        for (var key in response) {
            this._buildBoxItem(response[key].url);
        }
    },
    insertFromSelect: function(e) {
        e.preventDefault();

        var $target = this.dom(e.target);
        this._buildBoxItem($target.attr('data-url'));
    },
    insert: function() {
        // instance
        var instance = this.app.create('block.bs-carousel');
        this.app.block.add({ instance: instance });

        // save
        this.save();
    },
    save: function() {
        var current = this.app.block.get();
        var $elms = this.$box.find('img');

        if ($elms.length === 0) {
            current.remove();
            this.app.popup.close();
            return;
        }

        var $block = current.getBlock();
        var $inner = $block.find('.carousel-inner');

        // indicators
        var $indicators = $block.find('.carousel-indicators');
        var blockId = current.getId();

        // clear
        $indicators.html('');
        $inner.html('');

        $elms.each(function($node, i) {
            var src = $node.attr('src');

            var $item = this.dom('<div>').addClass('carousel-item');
            var $img = this.dom('<img>').addClass('d-block w-100').attr('src', src);
            var $indicator = this.dom('<li>').attr({ 'data-slide-to': i, 'data-target': '#' + blockId });

            $item.append($img);
            $inner.append($item);
            $indicators.append($indicator);


        }.bind(this));

        $inner.find('.carousel-item').first().addClass('active');
        $indicators.find('li').first().addClass('active');

        // close popup
        this.app.popup.close();
    },

    // private
    _removeItem: function(e) {
        var $target = this.dom(e.target).closest('.' + this.prefix + '-slideshow-item');
        $target.fadeOut(function($node) {
            $node.remove();
        }.bind(this));
    },
    _buildBoxItem: function(src) {
        var $item = this.dom('<span>').addClass(this.prefix + '-slideshow-item');
        var $remover = this.dom('<span>').addClass(this.prefix + '-upload-remove');
        var $img = this.dom('<img>').attr('src', src);

        $remover.one('click', this._removeItem.bind(this));

        $item.append($img);
        $item.append($remover);
        this.$box.append($item);
    },
    _buildUpload: function($item, callback) {
        if (!this.opts.carousel.upload) return;

        var params = {
            box: true,
            placeholder: this.lang.get('image.upload-new-placeholder'),
            url: this.opts.carousel.upload,
            name: this.opts.carousel.name,
            data: this.opts.carousel.data,
            multiple: this.opts.carousel.multiple,
            success: callback,
            error: 'image.error'
        };

        this.app.create('upload', $item, params);
    }
});