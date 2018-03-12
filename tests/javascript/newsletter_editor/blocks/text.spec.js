const expect = global.expect;
const sinon = global.sinon;

define([
  'newsletter_editor/App',
  'newsletter_editor/blocks/text'
], function (EditorApplication, TextBlock) {
  describe('Text', function () {
    describe('model', function () {
      var model;
      var sandbox;
      beforeEach(function () {
        global.stubChannel(EditorApplication);
        global.stubConfig(EditorApplication);
        model = new (TextBlock.TextBlockModel)();
        sandbox = sinon.sandbox.create();
      });

      afterEach(function () {
        sandbox.restore();
      });

      it('has a text type', function () {
        expect(model.get('type')).to.equal('text');
      });

      it('has text', function () {
        expect(model.get('text')).to.be.a('string');
      });

      it('uses defaults from config when they are set', function () {
        global.stubConfig(EditorApplication, {
          blockDefaults: {
            text: {
              text: 'some custom config text'
            }
          }
        });
        model = new (TextBlock.TextBlockModel)();

        expect(model.get('text')).to.equal('some custom config text');
      });

      it('do not update blockDefaults.text when changed', function () {
        var stub = sandbox.stub(EditorApplication.getConfig(), 'set');
        model.trigger('change');
        expect(stub.callCount).to.equal(0);
      });
    });

    describe('block view', function () {
      var model;
      var view;
      global.stubConfig(EditorApplication);
      model = new (TextBlock.TextBlockModel)();
      view = new (TextBlock.TextBlockView)({ model: model });

      it('renders', function () {
        expect(view.render).to.not.throw();
        expect(view.$('.mailpoet_content')).to.have.length(1);
      });

      describe('once rendered', function () {
        model = new (TextBlock.TextBlockModel)();

        beforeEach(function () {
          global.stubConfig(EditorApplication);
          view = new (TextBlock.TextBlockView)({ model: model });
          view.render();
        });

        it('has a deletion tool', function () {
          expect(view.$('.mailpoet_delete_block')).to.have.length(1);
        });

        it('has a move tool', function () {
          expect(view.$('.mailpoet_move_block')).to.have.length(1);
        });

        it('does not have a settings tool', function () {
          expect(view.$('.mailpoet_edit_block')).to.have.length(0);
        });
      });
    });
  });
});
