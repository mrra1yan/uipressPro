const uipAdminPagesOptions = {
  data() {
    return {
      loading: true,
      screenWidth: window.innerWidth,
      translations: uipTranslations,
      dataConnect: uipMasterPrefs.dataConnect,
      user: {
        allMenus: [],
        currentMenu: {
          items: [],
          name: "",
          status: true,
          roleMode: "inclusive",
          appliedTo: [],
        },
        currentItem: [],
      },
      master: {
        menuItems: [],
        searchString: "",
      },
      ui: {
        activeTab: "items",
        editingMode: false,
        editPanel: false,
      },
    };
  },
  created: function () {
    window.addEventListener("resize", this.getScreenWidth);
  },
  computed: {
    originalMenu() {
      var originaltmen = this.master.menuItems;
      return originaltmen;
    },
    filteredMenu() {
      var currentmen = this.user.currentMenu.items;
      return currentmen;
    },
  },
  mounted: function () {
    this.getAdminPages();
    this.loading = false;
  },
  methods: {
    itemsMoved() {
      items = this.user.currentMenu.items;
      for (let i = 0; i < items.length; i++) {
        if (!items[i].submenu) {
          items[i].submenu = [];
        }
      }
    },
    cloneMenuItem(menuitem) {
      return JSON.parse(JSON.stringify(menuitem));
    },
    exportMenu(themenu) {
      self = this;
      ALLoptions = JSON.stringify(themenu);

      var today = new Date();
      var dd = String(today.getDate()).padStart(2, "0");
      var mm = String(today.getMonth() + 1).padStart(2, "0"); //January is 0!
      var yyyy = today.getFullYear();

      date_today = mm + "_" + dd + "_" + yyyy;
      filename = "uipress_menu_" + date_today + ".json";

      var dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(ALLoptions);
      var dlAnchorElem = document.getElementById("uipress-export-menus");
      dlAnchorElem.setAttribute("href", dataStr);
      dlAnchorElem.setAttribute("download", filename);
      dlAnchorElem.click();
    },
    import_menu() {
      self = this;

      var thefile = jQuery("#uipress_import_menu")[0].files[0];

      if (thefile.type != "application/json") {
        window.alert("Please select a valid JSON file.");
        return;
      }

      if (thefile.size > 100000) {
        window.alert("File is to big.");
        return;
      }

      var file = document.getElementById("uipress_import_menu").files[0];
      var reader = new FileReader();
      reader.readAsText(file, "UTF-8");

      reader.onload = function (evt) {
        json_settings = evt.target.result;
        parsed = JSON.parse(json_settings);

        if (parsed != null) {
          parsed.id = null;
          ///GOOD TO GO;
          self.user.currentMenu = parsed;
          uipNotification("Menu imported", { pos: "bottom-left", status: "success" });
          self.saveSettings();
        } else {
          uipNotification("something wrong", { pos: "bottom-left", status: "danger" });
        }
      };
    },
    createNewMenu() {
      this.user.currentMenu.items = [];
      this.user.currentMenu.id = "";
      this.user.currentMenu.name = "Draft";
      this.user.currentMenu.status = true;
      this.user.currentMenu.roleMode = "inclusive";
      this.user.currentMenu.appliedTo = [];
      this.ui.editingMode = true;
    },
    confirmDelete(themenu) {
      self = this;
      if (confirm(self.translations.confirmDelete)) {
        self.deleteMenu(themenu);
      }
    },
    switchStatus(menuid, menustatus) {
      self = this;

      jQuery.ajax({
        url: uip_ajax.ajax_url,
        type: "post",
        data: {
          action: "uipress_switch_menu_status",
          security: uip_ajax.security,
          menuid: menuid,
          status: menustatus,
        },
        success: function (response) {
          data = JSON.parse(response);

          if (data.error) {
            ///SOMETHING WENT WRONG
            uipNotification(data.error, { pos: "bottom-left", status: "danger" });
            return;
          }

          uipNotification(data.message, { pos: "bottom-left", status: "success" });
        },
      });
    },
    duplicateMenu(themenu) {
      self = this;

      if (!themenu) {
        return;
      }

      jQuery.ajax({
        url: uip_ajax.ajax_url,
        type: "post",
        data: {
          action: "uipress_duplicate_menu",
          security: uip_ajax.security,
          menu: themenu,
        },
        success: function (response) {
          data = JSON.parse(response);

          if (data.error) {
            ///SOMETHING WENT WRONG
            uipNotification(data.error, { pos: "bottom-left", status: "danger" });
            return;
          }

          uipNotification(data.message, { pos: "bottom-left", status: "success" });

          self.getAdminPages();
        },
      });
    },
    editCurrentPage() {
      const editor = grapesjs.init({
        // Indicate where to init the editor. You can also pass an HTMLElement
        container: "#uip-admin-page-preview",
        // Get the content for the canvas directly from the element
        // As an alternative we could use: `components: '<h1>Hello World Component!</h1>'`,
        fromElement: true,
        // Size of the editor
        height: "100%",
        width: "auto",
        // Disable the storage manager for the moment
        storageManager: false,
        //plugins: ["grapesjs-plugin-toolbox"],

        // Avoid any default panel
      });

      editor.getConfig().showDevices = 0;

      editor.Panels.addPanel({
        id: "devices",
        buttons: [
          {
            id: "set-device-desktop",
            command: function (e) {
              return e.setDevice("Desktop");
            },
            className: "fa fa-desktop",
            active: 1,
          },
          {
            id: "set-device-tablet",
            command: function (e) {
              return e.setDevice("Tablet");
            },
            className: "fa fa-tablet",
          },
          {
            id: "set-device-mobile",
            command: function (e) {
              return e.setDevice("Mobile portrait");
            },
            className: "fa fa-mobile",
          },
        ],
      });
    },
    deleteMenu(themenu) {
      self = this;

      if (!themenu.id) {
        return;
      }

      jQuery.ajax({
        url: uip_ajax.ajax_url,
        type: "post",
        data: {
          action: "uipress_delete_menu",
          security: uip_ajax.security,
          menuid: themenu.id,
        },
        success: function (response) {
          data = JSON.parse(response);

          if (data.error) {
            ///SOMETHING WENT WRONG
            uipNotification(data.error, { pos: "bottom-left", status: "danger" });
            return;
          }

          uipNotification(data.message, { pos: "bottom-left", status: "success" });

          self.getAdminPages();
        },
      });
    },
    openMenu(themenu) {
      this.user.currentMenu = themenu;
      this.ui.editingMode = true;
    },
    getAdminPages() {
      self = this;

      jQuery.ajax({
        url: uip_ajax.ajax_url,
        type: "post",
        data: {
          action: "uipress_get_admin_pages",
          security: uip_ajax.security,
        },
        success: function (response) {
          data = JSON.parse(response);

          if (data.error) {
            ///SOMETHING WENT WRONG
            uipNotification(data.error, { pos: "bottom-left", status: "danger" });
            return;
          }

          self.user.allMenus = data.menus;
        },
      });
    },
    saveSettings() {
      self = this;

      menuitems = JSON.stringify(self.user.currentMenu);

      jQuery.ajax({
        url: uip_ajax.ajax_url,
        type: "post",
        data: {
          action: "uipress_save_admin_page",
          security: uip_ajax.security,
          menu: menuitems,
        },
        success: function (response) {
          data = JSON.parse(response);

          if (data.error) {
            ///SOMETHING WENT WRONG
            uipNotification(data.error, { pos: "bottom-left", status: "danger" });
            return;
          }
          self.user.currentMenu.id = data.menuID;
          self.getAdminPages();

          uipNotification(data.message, { pos: "bottom-left", status: "success" });
        },
      });
    },
    onChange() {},
    addDivider() {
      item = {
        name: "",
        type: "sep",
      };

      this.user.currentMenu.items.push(item);
    },
    addBlank() {
      item = {
        name: "Blank",
        type: "menu",
        href: "#",
        submenu: [],
        icon: "<span class='material-icons-outlined a2020-menu-icon'>check_box_outline_blank</span>",
      };

      this.user.currentMenu.items.push(item);
    },
    getDataFromComp(originalcode, editedcode) {
      return editedcode;
    },
    getScreenWidth() {
      this.screenWidth = window.innerWidth;
    },
    isSmallScreen() {
      if (this.screenWidth < 1000) {
        return true;
      } else {
        return false;
      }
    },
    addToMenu(item) {
      item.type = "menu";

      if (!item.submenu || !Array.isArray(item.submenu)) {
        item.submenu = [];
      } else {
        for (let i = 0; i < item.submenu.length; i++) {
          item.submenu[i].submenu = [];
        }
      }
      this.user.currentMenu.items.push(JSON.parse(JSON.stringify(item)));
    },
    editMenuItem(item) {
      this.user.currentItem = item;

      if (this.user.currentItem.blankPage && this.user.currentItem.blankPage != "") {
        option = this.user.currentItem.blankPage;
        if (option == "1" || option == true || option == "true") {
          this.user.currentItem.blankPage = true;
        } else {
          this.user.currentItem.blankPage = false;
        }
      }

      this.ui.editPanel = true;
    },
    removeMenuItem(currentindex) {
      this.user.currentMenu.items.splice(currentindex, 1);
    },
    removeSubMenuItem(currentindex, parentindex) {
      this.user.currentMenu.items[parentindex].submenu.splice(currentindex, 1);
    },
    getdatafromIcon(chosenicon) {
      if (chosenicon == "removeicon") {
        returndata = "";
      } else {
        returndata = '<span class="material-icons-outlined a2020-menu-icon">' + chosenicon + "</span>";
      }
      return returndata;
    },
  },
};

const uipAdminPagesCreator = uipVue.createApp(uipAdminPagesOptions);

/////////////////////////
//Multi Select Component
/////////////////////////
uipAdminPagesCreator.component("multi-select", {
  data: function () {
    return {
      thisSearchInput: "",
      options: [],
      ui: {
        dropOpen: false,
      },
    };
  },
  props: {
    selected: Array,
    name: String,
    placeholder: String,
    single: Boolean,
  },
  watch: {
    thisSearchInput: function (newValue, oldValue) {
      self = this;

      if (newValue.length > 0) {
        jQuery.ajax({
          url: uip_ajax.ajax_url,
          type: "post",
          data: {
            action: "uip_get_users_and_roles",
            security: uip_ajax.security,
            searchString: newValue,
          },
          success: function (response) {
            data = JSON.parse(response);

            if (data.error) {
              ///SOMETHING WENT WRONG
              uipNotification(data.error, { pos: "bottom-left", status: "danger" });
              return;
            }

            self.options = data.roles;
          },
        });
      }
    },
  },
  methods: {
    //////TITLE: ADDS A SLECTED OPTION//////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////DESCRIPTION: ADDS A SELECTED OPTION FROM OPTIONS
    addSelected(selectedoption, options) {
      if (this.single == true) {
        options[0] = selectedoption;
      } else {
        options.push(selectedoption);
      }
    },
    //////TITLE: REMOVES A SLECTED OPTION//////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////DESCRIPTION: ADDS A SELECTED OPTION FROM OPTIONS
    removeSelected(option, options) {
      const index = options.indexOf(option);
      if (index > -1) {
        options = options.splice(index, 1);
      }
    },

    //////TITLE:  CHECKS IF SELECTED OR NOT//////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////DESCRIPTION: ADDS A SELECTED OPTION FROM OPTIONS
    ifSelected(option, options) {
      const index = options.indexOf(option);
      if (index > -1) {
        return false;
      } else {
        return true;
      }
    },
    //////TITLE:  CHECKS IF IN SEARCH//////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////DESCRIPTION: CHECKS IF ITEM CONTAINS STRING
    ifInSearch(option, searchString) {
      item = option.toLowerCase();
      string = searchString.toLowerCase();

      if (item.includes(string)) {
        return true;
      } else {
        return false;
      }
    },
    onClickOutside(event) {
      const path = event.path || (event.composedPath ? event.composedPath() : undefined);
      // check if the MouseClick occurs inside the component
      if (path && !path.includes(this.$el) && !this.$el.contains(event.target)) {
        this.closeThisComponent(); // whatever method which close your component
      }
    },
    openThisComponent() {
      this.ui.dropOpen = true; // whatever codes which open your component
      // You can also use Vue.$nextTick or setTimeout
      requestAnimationFrame(() => {
        document.documentElement.addEventListener("click", this.onClickOutside, false);
      });
    },
    closeThisComponent() {
      this.ui.dropOpen = false; // whatever codes which close your component
      document.documentElement.removeEventListener("click", this.onClickOutside, false);
    },
  },
  template:
    '<div class="uip-position-relative" @click="openThisComponent">\
      <div class="uip-margin-bottom-xs uip-padding-left-xxs uip-padding-right-xxs uip-padding-top-xxs uip-background-default uip-border uip-border-round uip-w-100p uip-cursor-pointer uip-h-32 uip-border-box"> \
        <div class="uip-flex uip-flex-center">\
          <div class="uip-flex-grow uip-margin-right-s">\
            <div v-if="selected.length < 1" style="margin-top:2px;">\
              <span class="uk-text-meta">{{name}}...</span>\
            </div>\
            <span v-if="selected.length > 0" v-for="select in selected" class="uip-background-primary-wash uip-border-round uip-padding-xxs uip-display-inline-block uip-margin-right-xxs uip-margin-bottom-xxs">\
              <div class="uip-text-normal">\
                {{select}}\
                <span class="uip-margin-left-xxs" href="#" @click="removeSelected(select,selected)">x</span>\
              </div>\
            </span>\
          </div>\
          <span class="material-icons-outlined uip-text-muted">expand_more</span>\
        </div>\
      </div>\
      <div v-if="ui.dropOpen" class="uip-position-absolute uip-padding-s uip-background-default uip-border uip-border-round uip-shadow uip-w-100p uip-border-box uip-z-index-9">\
        <div class="uip-flex uip-background-muted uip-padding-xxs uip-margin-bottom-s uip-border-round">\
          <span class="material-icons-outlined uip-text-muted uip-margin-right-xs">search</span>\
          <input class="uip-blank-input uip-flex-grow" type="search"  \
          :placeholder="placeholder" v-model="thisSearchInput" autofocus>\
        </div>\
        <div class="">\
          <template v-for="option in options">\
            <span  class="uip-background-muted uip-border-round uip-padding-xxs uip-display-inline-block uip-margin-right-xxs uip-margin-bottom-xxs uip-text-normal" \
            @click="addSelected(option.name, selected)" \
            v-if="ifSelected(option.name, selected) && ifInSearch(option.name, thisSearchInput)" \
            style="cursor: pointer">\
            {{option.label}}\
            </span>\
          </template>\
        </div>\
      </div>\
    </div>',
});

uipAdminPagesCreator.component("icon-select", {
  emits: ["iconchange"],
  props: {
    menuitemicon: String,
    translations: Object,
  },
  data: function () {
    return {
      thisSearchInput: "",
      options: allGoogleIcons,
      currentPage: 0,
      iconsPerPage: 56,
      totalIcons: 0,
      maxPages: 0,
      ui: {
        options: false,
      },
    };
  },
  watch: {
    thisSearchInput: function (newValue, oldValue) {},
  },
  computed: {
    allIcons() {
      let self = this;
      masteroptions = self.options;
      returndata = [];
      temparray = [];
      searchinput = self.thisSearchInput.toLowerCase();

      if (self.currentPage < 0) {
        self.currentPage = 0;
      }
      self.totalIcons = self.options.length;
      self.maxPages = Math.ceil(self.options.length / this.iconsPerPage);

      if (self.currentPage > self.maxPages) {
        self.currentPage = self.maxPages;
      }

      startPos = self.currentPage * self.iconsPerPage;
      endPos = startPos + self.iconsPerPage;

      if (searchinput.length > 0) {
        self.currentPage = 0;

        for (let i = 0; i < masteroptions.length; i++) {
          name = masteroptions[i].toLowerCase();
          if (name.includes(searchinput)) {
            temparray.push(masteroptions[i]);
          }
        }

        returndata = temparray.slice(startPos, endPos);
        self.totalIcons = returndata.length;
        self.maxPages = Math.ceil(returndata.length / this.iconsPerPage);
      } else {
        returndata = this.options.slice(startPos, endPos);
      }

      return returndata;
    },
  },
  methods: {
    chosenicon(thicon) {
      this.$emit("iconchange", thicon);
    },
    removeIcon() {
      thicon = "removeicon";
      this.$emit("iconchange", thicon);
    },
    //////TITLE: ADDS A SLECTED OPTION//////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////DESCRIPTION: ADDS A SELECTED OPTION FROM OPTIONS
    addSelected(selectedoption, options) {
      if (this.single == true) {
        options[0] = selectedoption;
      } else {
        options.push(selectedoption);
      }
    },
    //////TITLE: REMOVES A SLECTED OPTION//////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////DESCRIPTION: ADDS A SELECTED OPTION FROM OPTIONS
    removeSelected(option, options) {
      const index = options.indexOf(option);
      if (index > -1) {
        options = options.splice(index, 1);
      }
    },

    //////TITLE:  CHECKS IF SELECTED OR NOT//////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////DESCRIPTION: ADDS A SELECTED OPTION FROM OPTIONS
    ifSelected(option, options) {
      const index = options.indexOf(option);
      if (index > -1) {
        return false;
      } else {
        return true;
      }
    },
    //////TITLE:  CHECKS IF IN SEARCH//////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////DESCRIPTION: CHECKS IF ITEM CONTAINS STRING
    ifInSearch(option, searchString) {
      item = option.toLowerCase();
      string = searchString.toLowerCase();

      if (item.includes(string)) {
        return true;
      } else {
        return false;
      }
    },
    onClickOutside(event) {
      const path = event.path || (event.composedPath ? event.composedPath() : undefined);
      // check if the MouseClick occurs inside the component
      if (path && !path.includes(this.$el) && !this.$el.contains(event.target)) {
        this.closeThisComponent(); // whatever method which close your component
      }
    },
    openThisComponent() {
      this.ui.options = this.ui.options != true; // whatever codes which open your component
      // You can also use Vue.$nextTick or setTimeout
      requestAnimationFrame(() => {
        document.documentElement.addEventListener("click", this.onClickOutside, false);
      });
    },
    closeThisComponent() {
      this.ui.options = false; // whatever codes which close your component
      document.documentElement.removeEventListener("click", this.onClickOutside, false);
    },
    nextPage() {
      this.currentPage = this.currentPage + 1;
    },
    previousPage() {
      this.currentPage = this.currentPage - 1;
    },
  },
  template:
    '<div>\
      <div class="uip-flex">\
        <span v-if="menuitemicon"\
        class="material-icons-outlined uip-background-muted uip-padding-xxs uip-border-round hover:uip-background-grey uip-cursor-pointer uip-margin-right-xs"\
        v-html="menuitemicon" ></span>\
        <button @click="openThisComponent" class="uip-button-default" type="button">{{translations.chooseIcon}}</button>\
      </div>\
      <div v-if="ui.options" \
      class="uip-position-absolute uip-padding-s uip-background-default uip-border-round uip-shadow uip-drop-bottom uip-w-300">\
        <!-- SEARCH COMP -->\
        <div class="uip-margin-bottom-m uip-padding-xxs uip-background-muted uip-border-round">\
          <div class="uip-flex uip-flex-center">\
            <span class="uip-margin-right-xs uip-text-muted">\
              <span class="material-icons-outlined">manage_search</span>\
            </span> \
            <input type="search" v-model="thisSearchInput" :placeholder="translations.search" class="uip-blank-input uip-min-width-0 uip-flex-grow">\
          </div>\
        </div>\
        <!-- END SEARCH COMP -->\
        <div class="uip-flex uip-flex-wrap uip-flex-start uip-margin-bottom-s">\
          <template v-for="option in allIcons">\
             <span class="uip-margin-right-xs uip-margin-bottom-xs material-icons-outlined uip-background-muted uip-padding-xxs uip-border-round hover:uip-background-grey uip-cursor-pointer uip-flex-no-grow uip-max-w-32" @click="chosenicon(option)">\
               {{option}}\
             </span>\
          </template>\
        </div>\
        <div class="uip-flex">\
          <button v-if="totalIcons > iconsPerPage" class="uip-button-default material-icons-outlined uip-margin-right-xs" @click="previousPage()" type="button">chevron_left</button>\
          <button v-if="totalIcons > iconsPerPage" class="uip-button-default material-icons-outlined" @click="nextPage()" type="button">chevron_right</button>\
          <div class="uip-flex-grow uip-text-right">\
            <button @click="removeIcon()" class="uip-button-danger" type="button">Clear Icon</button>\
          </div>\
        </div>\
      </div>\
    </div>',
});

uipAdminPagesCreator.component("uip-dropdown", {
  props: {
    type: String,
    icon: String,
    pos: String,
    translation: String,
    size: String,
    primary: Boolean,
  },
  data: function () {
    return {
      modelOpen: false,
    };
  },
  mounted: function () {},
  methods: {
    onClickOutside(event) {
      const path = event.path || (event.composedPath ? event.composedPath() : undefined);
      // check if the MouseClick occurs inside the component
      if (path && !path.includes(this.$el) && !this.$el.contains(event.target)) {
        this.closeThisComponent(); // whatever method which close your component
      }
    },
    openThisComponent() {
      this.modelOpen = this.modelOpen != true; // whatever codes which open your component
      // You can also use Vue.$nextTick or setTimeout
      requestAnimationFrame(() => {
        document.documentElement.addEventListener("click", this.onClickOutside, false);
      });
    },
    closeThisComponent() {
      this.modelOpen = false; // whatever codes which close your component
      document.documentElement.removeEventListener("click", this.onClickOutside, false);
    },
    getClass() {
      if (this.pos == "botton-left") {
        return "uip-margin-top-s uip-right-0";
      }
      if (this.pos == "botton-center") {
        return "uip-margin-top-s uip-right-center";
      }
      if (this.pos == "top-left") {
        return "uip-margin-bottom-s uip-right-0 uip-bottom-100p";
      }
    },
    getPaddingClass() {
      if (!this.size) {
        return "uip-padding-xs";
      }
      if (this.size == "small") {
        return "uip-padding-xxs";
      }
      if (this.size == "large") {
        return "uip-padding-s";
      }
      return "uip-padding-xs";
    },
    getPrimaryClass() {
      if (!this.primary) {
        return "uip-button-default";
      }
      if (this.primary) {
        return "uip-button-primary uip-text-bold";
      }
      return "uip-button-default";
    },
  },
  template:
    '<div class="uip-position-relative">\
      <div class="uip-display-inline-block">\
        <div v-if="type == \'icon\'" @click="openThisComponent" class="uip-background-muted uip-border-round hover:uip-background-grey uip-cursor-pointer  material-icons-outlined" type="button" :class="getPaddingClass()">{{icon}}</div>\
        <button v-if="type == \'button\'" @click="openThisComponent" class="uip-button-default" :class="[getPaddingClass(), getPrimaryClass() ]" type="button">{{translation}}</button>\
      </div>\
      <div v-if="modelOpen" :class="getClass()"\
      class="uip-position-absolute uip-padding-s uip-background-default uip-border-round uip-shadow uip-min-w-200 uip-z-index-9999">\
        <slot></slot>\
      </div>\
    </div>',
});

uipAdminPagesCreator.mount("#admin-pages-creator-app");
