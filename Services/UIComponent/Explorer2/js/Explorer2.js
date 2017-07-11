
/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

il.Explorer2 = {
	
	selects: {},
	
	configs: {},
	
	init: function (config, js_tree_config) {
//console.log(js_tree_config);
		if (config.ajax) {
			js_tree_config.html_data.ajax = {url: config.url + "&exp_cmd=getNodeAsync",
				data: function(n) {
					//console.log(this); // exp_cont missing
					return {node_id: n.attr ? n.attr("id") : "",
						exp_cont: config.container_id
					};
			}};
			
			js_tree_config.html_data.data = $("#" + config.container_id).html();
		}
		il.Explorer2.configs[config.container_id] = config;
		$("#" + config.container_id).on("loaded.jstree", function (event, data) {
				var i;
				$("#" + config.container_outer_id).removeClass("ilNoDisplay");
				for (i = 0; i < config.second_hnodes.length; i++) {
					$("#" + config.second_hnodes[i]).addClass("ilExplSecHighlight");
				}
			}).on("open_node.jstree close_node.jstree", function (event, data) {
				il.Explorer2.toggle(event, data);
			}).jstree(js_tree_config);
	},
	
	toggle: function(event, data) {
		var type = event.type, // "open_node" or "close_node"
			id = data.rslt.obj[0].id, // id of li element
			container_id = event.target.id,
			t = il.Explorer2, url;
		//alexedit start
		$('#' + id +' .ilExp2NodeContent').off('hover');
		$('#' + id +' .ilExp2NodeContent').hover(
			function(){
                if ($(this).hasClass("ilExp2NodeContent_hover")) return;

                var pright=$("#" +container_id).offset().left+$("#" +container_id).innerWidth();
				var left=$(this).offset().left;
				if (left+$(this).outerWidth() < pright) return;
				var top=$(this).position().top;
				if ($("#left_nav").css('position')=='fixed') top = top + parseInt($("#left_nav").css('top'));
					else top = top - $(document).scrollTop();
				$(this).css("left",left);
				$(this).css("top",top);	
				$(this).addClass("ilExp2NodeContent_hover");		
			},function(){
				$(this).removeClass("ilExp2NodeContent_hover");	
			}
		);
		//alexedit ende
			
		// the args[2] parameter is true for the initially
		// opened nodes, but not, if manually opened
		// this is somhow undocumented, but it works
		if (type == "open_node" && data.args[2]) {
			return;
		}
		
		//console.log(event.target.id);
		//console.log(type + ": " + id);
		//console.log(t.configs[container_id].url);
		url = t.configs[container_id].url;
		if (url == '') {
			return;
		}
		if (type == "open_node") {
			url = url + "&exp_cmd=openNode";
		} else {
			url = url + "&exp_cmd=closeNode";
		}
		url = url + "&exp_cont=" + container_id + "&node_id=" + id;
		
		il.Util.sendAjaxGetRequestToUrl(url, {}, {}, null);
	},
	
	//
	// ExplorerSelectInputGUI related functions
	//
	
	// init select input
	initSelect: function(id) {
		$("#" + id + "_select").on("click", function (ev) {
			il.UICore.unloadWrapperFromRightPanel();
			il.UICore.showRightPanel();
			il.UICore.loadWrapperToRightPanel(id + "_expl_wrapper");
			return false;
		});
		$("#" + id + "_reset").on("click", function (ev) {
			$("#" + id + "_hid").empty();
			$("#" + id + "_cont_txt").empty();
			$('#' + id + '_expl_content input[type="checkbox"]').each(function() {
				this.checked = false;
			});

			return false;
		});
		$("#" + id + "_expl_content a.ilExplSelectInputButS").on("click", function (ev) {
			var t = sep = "";
			// create hidden inputs with values
			$("#" + id + "_hid").empty();
			$("#" + id + "_cont_txt").empty();
			$('#' + id + '_expl_content input[type="checkbox"]').each(function() {
				var n = this.name.substr(0, this.name.length - 6) + "[]",
					ni = "<input type='hidden' name='" + n + "' value='" + this.value + "' />";
				if (this.checked) {
					t = t + sep + $(this).parent().find("span.ilExp2NodeContent").html();
					sep = ", ";
					$("#" + id + "_hid").append(ni);
				}
			});
			$('#' + id + '_expl_content input[type="radio"]').each(function() {
				var n = this.name.substr(0, this.name.length - 4),
					ni = "<input type='hidden' name='" + n + "' value='" + this.value + "' />";
				if (this.checked) {
					t = t + sep + $(this).parent().find("span.ilExp2NodeContent").html();
					sep = ", ";
					$("#" + id + "_hid").append(ni);
				}
			});
			$("#" + id + "_cont_txt").html(t);
			il.UICore.hideRightPanel();
			
			return false;
		});		
		$("#" + id + "_expl_content a.ilExplSelectInputButC").on("click", function (ev) {
			il.UICore.hideRightPanel();
			return false;
		});		
	},
	
	selectOnClick: function (node_id) {
		$('#' + node_id + ' input[type="checkbox"]:first').each(function() {
			this.checked = !this.checked;
		});
		$('#' + node_id + ' input[type="radio"]:first').each(function() {
			this.checked = true;
		});
		return false;
	}
}
