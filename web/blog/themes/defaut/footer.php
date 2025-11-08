<?php if (!defined('PLX_ROOT'))
	exit; ?>

<footer class="footer">
	<div class="container">
		<p>
			<?php $plxShow->mainTitle('link'); ?> - <?php $plxShow->subTitle(); ?> &copy; 2025
		</p>
		<!-- <p>
				</?php $plxShow->lang('POWERED_BY') ?>&nbsp;<a href="</?= PLX_URL_REPO?>" title="</?php $plxShow->lang('PLUXML_DESCRIPTION') ?>">PluXml</a>
				</?php $plxShow->lang('IN') ?>&nbsp;</?php $plxShow->chrono(); ?>&nbsp;
				</?php $plxShow->httpEncoding() ?>&nbsp;-
				<a rel="nofollow" href="</?php $plxShow->urlRewrite('core/admin/'); ?>" title="</?php $plxShow->lang('ADMINISTRATION') ?>"></?php $plxShow->lang('ADMINISTRATION') ?></a>
			</p> -->

		<p>
			<a onclick="loadMarkdown('roadmap')">Roadmap</a> |
			<a onclick="loadMarkdown('changelog')">Changelog</a>
		</p>

		<ul class="menu">
			<!-- </?php  if($plxShow->plxMotor->aConf['enable_rss']) { ?>
				<li><a href="</?php $plxShow->urlRewrite('feed.php?rss') ?>" title="</?php $plxShow->lang('ARTICLES_RSS_FEEDS'); ?>"></?php $plxShow->lang('ARTICLES'); ?></a></li>
				</?php } ?>
				</?php if($plxShow->plxMotor->aConf['enable_rss_comment']) { ?>
					<li><a href="</?php $plxShow->urlRewrite('feed.php?rss/commentaires'); ?>" title="</?php $plxShow->lang('COMMENTS_RSS_FEEDS') ?>"></?php $plxShow->lang('COMMENTS'); ?></a></li>
				</?php  } ?> -->
			<li><a href="<?php $plxShow->urlRewrite('#top') ?>"
					title="<?php $plxShow->lang('GOTO_TOP') ?>"><?php $plxShow->lang('TOP') ?></a></li>
		</ul>
	</div>
</footer>
<div id="md-modal"
	style="display:none; position:fixed; top:10%; left:50%; transform:translateX(-50%); max-width:90%; max-height:80%; overflow:auto; background:white; padding:1rem; box-shadow:0 0 10px rgba(0,0,0,0.5); z-index:1000;">
	<button onclick="closeModal()" style="float:right;">✕</button>
	<div id="md-content"></div>
</div>
<script>
	function loadMarkdown(file) {
		fetch(`/blog/tech/${file}.md`)
			.then(res => res.ok ? res.text() : 'Error loading file')
			.then(md => {
				const html = marked.parse(md); // Requires marked.js
				document.getElementById('md-content').innerHTML = html;
				document.getElementById('md-modal').style.display = 'block';
			});
	}

	function closeModal() {
		document.getElementById('md-modal').style.display = 'none';
	}
</script>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
 <script src="/js/dark-mode.js"></script>
</body>

</html>