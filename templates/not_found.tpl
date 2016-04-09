<p>No se encontr&oacute; respuesta a su b&uacute;squeda: <b>{$term}</b></p>
{if $similars}
	<h2>Art&iacute;culos similares</h2>
	<ul>
	{foreach name=similars key=artid item=caption from=$similars}
		<li>{link href="DOCTOR ARTICULO {$artid}" caption="{$caption}"} </li>
	{/foreach}
	</ul>
{/if}